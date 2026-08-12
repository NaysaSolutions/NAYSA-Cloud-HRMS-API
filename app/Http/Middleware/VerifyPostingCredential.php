<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class VerifyPostingCredential
{
    public function handle(Request $request, Closure $next)
    {
        // Prefer JSON body; fall back to headers if provided
        $userCode     = $request->input('userCode', $request->header('X-User-Code'));
        $userPassword = $request->input('userPassword', $request->header('X-User-Password'));

        // Normalize
        $userCode = is_string($userCode) ? trim($userCode) : '';
        $userPassword = is_string($userPassword) ? $userPassword : '';

        if ($userCode === '' || $userPassword === '') {
            return response()->json([
                'success' => false,
                'error'   => 'MISSING_CREDENTIALS',
                'message' => 'Password is missing.',
            ], 422)->header('X-Posting-Result', 'MISSING_CREDENTIALS');
        }

        // Optional: ensure payload user matches authenticated user (if logged in)
        if (auth()->check()) {
            $authCode = data_get(auth()->user(), 'userCode') ?? data_get(auth()->user(), 'USER_CODE');
            if ($authCode && strcasecmp((string) $authCode, $userCode) !== 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'USER_MISMATCH',
                    'message' => 'Authenticated user does not match userCode.',
                ], 403)->header('X-Posting-Result', 'USER_MISMATCH');
            }
        }

        // ---- Verify against tenant..USERS (bcrypt in PASSWORD) ----
        $verified = false;

        try {
            $user = DB::connection('tenant')
                ->table('USERS')
                ->where('USER_CODE', $userCode)
                ->select(['USER_CODE', 'PASSWORD', 'ACTIVE'])
                ->first();

            // If inactive, treat as forbidden (auth/permission problem)
            if ($user && isset($user->ACTIVE) && strtoupper((string) $user->ACTIVE) !== 'Y') {
                return response()->json([
                    'success' => false,
                    'error'   => 'USER_INACTIVE',
                    'message' => 'User is inactive.',
                ], 403)->header('X-Posting-Result', 'USER_INACTIVE');
            }

            if ($user && is_string($user->PASSWORD) && $user->PASSWORD !== '') {
                $verified = Hash::check($userPassword, $user->PASSWORD);
            }
        } catch (\Throwable $e) {
            Log::warning('VerifyPostingCredential: USERS lookup failed', ['error' => $e->getMessage()]);

            // Fail closed but as soft validation (not a logout-triggering status)
            return response()->json([
                'success' => false,
                'error'   => 'LOOKUP_FAILED',
                'message' => 'Unable to verify credentials at this time.',
            ], 422)->header('X-Posting-Result', 'LOOKUP_FAILED');
        }

        if (!$verified) {
            // Slight delay to slow brute-force
            usleep(150000);

            Log::notice('Posting credential failed', [
                'ip'   => $request->ip(),
                'path' => $request->path(),
                'user' => $userCode,
            ]);

            // *** Important: 422, not 403 ***
            return response()->json([
                'success' => false,
                'error'   => 'INVALID_CREDENTIALS',
                'message' => 'Invalid password.',
            ], 422)->header('X-Posting-Result', 'INVALID_CREDENTIALS');
        }

        // Scrub secret from request payload so downstream can’t leak it
        $request->request->remove('userPassword');

        return $next($request);
    }
}
