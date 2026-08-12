<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckSingleSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // If there's no authenticated user, do nothing and let public routes work.
        if (!$user) {
            return $next($request);
        }

        $presented = $user->currentAccessToken(); // Sanctum PersonalAccessToken or null
        $pointerId = $user->current_token_id ? (int)$user->current_token_id : null;

        // Enforce only when a token is presented AND we have a stored pointer
        if ($presented && $pointerId !== null) {
            $presentedId = (int) $presented->id;

            if ($presentedId !== $pointerId) {
                try {
                    $presented->delete(); // cleanup stale token
                } catch (\Throwable $e) {
                    Log::warning("CheckSingleSession: delete stale token failed for {$user->USER_CODE}: {$e->getMessage()}");
                }

                Log::info("CheckSingleSession: token mismatch for {$user->USER_CODE}. presented={$presentedId}, expected={$pointerId}");

                return response()->json([
                    'status'  => 'error',
                    'code'    => 'DUPLICATE_LOGIN',
                    'message' => 'Your session is no longer valid. Please sign in again.',
                ], 401); // or 409
            }
        }

        return $next($request);
    }
}
