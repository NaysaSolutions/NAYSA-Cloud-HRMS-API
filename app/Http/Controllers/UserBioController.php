<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use lbuchs\WebAuthn\WebAuthn;

class UserBioController extends Controller
{
    /**
     * -------------------------------------------------------------
     * CONFIG
     * -------------------------------------------------------------
     */
    protected string $rpName = 'NAYSA Cloud';
    protected ?string $rpId = null;
    protected ?string $rootCertPath = null;

    /**
     * -------------------------------------------------------------
     * HELPERS
     * -------------------------------------------------------------
     */
    protected function getRpId(Request $request): string
    {
        if (!empty($this->rpId)) {
            return $this->rpId;
        }

        $host = $request->getHost();
        $host = preg_replace('/:\d+$/', '', $host);

        if (in_array($host, ['127.0.0.1', '::1'])) {
            return 'localhost';
        }

        return $host ?: 'localhost';
    }

    protected function getWebAuthn(Request $request): WebAuthn
    {
        $webAuthn = new WebAuthn($this->rpName, $this->getRpId($request));

        if (!empty($this->rootCertPath) && is_dir($this->rootCertPath)) {
            $webAuthn->addRootCertificates($this->rootCertPath);
        }

        return $webAuthn;
    }

    protected function b64urlEncode(?string $data): ?string
    {
        if ($data === null) {
            return null;
        }

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function b64urlDecode(?string $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }

        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    protected function decodeBinaryMimeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if (preg_match('/^=\?BINARY\?B\?(.+)\?=$/i', $trimmed, $matches)) {
            return base64_decode($matches[1]);
        }

        return $trimmed;
    }

    protected function bytesToB64Url($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = $this->decodeBinaryMimeString($value);
            return $this->b64urlEncode($decoded);
        }

        if (is_array($value)) {
            $bin = '';
            foreach ($value as $byte) {
                $bin .= chr((int) $byte);
            }
            return $this->b64urlEncode($bin);
        }

        return null;
    }

// public function loginOptionsPasswordless(Request $request)
// {
//     try {
//         $challengeRaw = random_bytes(32);
//         $challengeB64 = $this->b64urlEncode($challengeRaw);

//         Session::put('user_bio.passwordless.challenge', $challengeB64);

//         \Log::info('PASSWORDLESS OPTIONS SESSION', [
//             'session_id' => $request->session()->getId(),
//             'challenge_exists' => Session::has('user_bio.passwordless.challenge'),
//         ]);

//         $payload = [
//             'challenge' => $challengeB64,
//             'timeout' => 240000,
//             'userVerification' => 'required',
//             'rpId' => $this->getRpId($request),
//         ];

//         return $this->success($payload, 'Passwordless login options generated.');
//     } catch (\Throwable $e) {
//         \Log::error('PASSWORDLESS OPTIONS ERROR', [
//             'message' => $e->getMessage(),
//             'file' => $e->getFile(),
//             'line' => $e->getLine(),
//         ]);

//         return $this->fail($e->getMessage(), 500);
//     }
// }

public function loginOptionsPasswordless(Request $request)
{
    try {
        $challengeRaw = random_bytes(32);
        $challengeB64 = $this->b64urlEncode($challengeRaw);

        Session::put('user_bio.passwordless.challenge', $challengeB64);

        return $this->success([
            'challenge' => $challengeB64,
            'timeout' => 240000,
            'userVerification' => 'required',
            'rpId' => $this->getRpId($request),
        ], 'Passwordless login options generated.');
    } catch (\Throwable $e) {
        return $this->fail($e->getMessage(), 500);
    }
}
  

public function loginVerifyPasswordless(Request $request)
{
    try {
        $request->validate([
            'credential.id' => 'required|string',
            'credential.rawId' => 'nullable|string',
            'credential.type' => 'nullable|string',
            'credential.response.clientDataJSON' => 'required|string',
            'credential.response.authenticatorData' => 'required|string',
            'credential.response.signature' => 'required|string',
            'credential.response.userHandle' => 'nullable|string',
        ]);

        $challengeB64 = Session::get('user_bio.passwordless.challenge');

        if (!$challengeB64) {
            return $this->fail('Login session expired or invalid.', 419);
        }

        $challenge = $this->b64urlDecode($challengeB64);

        if (!$challenge) {
            return $this->fail('Stored login challenge is invalid.', 419);
        }

        // Prefer rawId when available
        $credentialId = trim((string) ($request->input('credential.rawId') ?: $request->input('credential.id')));
        $bio = $this->activeCredentialByCredentialId($credentialId);

        if (!$bio) {
            return $this->fail('No device credential found. Enter your User ID and try again.', 404);
        }

        $userCode = trim((string) $bio->USER_CODE);
        $user = $this->findUser($userCode);

        if (!$user) {
            return $this->fail('User not found.', 404);
        }

        if (($user->ACTIVE ?? 'Y') !== 'Y') {
            return $this->fail('User is inactive.', 403);
        }

        $clientDataJSON = $this->b64urlDecode($request->input('credential.response.clientDataJSON'));
        $authenticatorData = $this->b64urlDecode($request->input('credential.response.authenticatorData'));
        $signature = $this->b64urlDecode($request->input('credential.response.signature'));
        $userHandle = $this->b64urlDecode($request->input('credential.response.userHandle'));
        $publicKeyRaw = base64_decode((string) $bio->PUBLIC_KEY, true);

        if (!$clientDataJSON || !$authenticatorData || !$signature) {
            return $this->fail('Invalid biometric response payload.', 400);
        }

        if (!$publicKeyRaw) {
            return $this->fail('Stored public key is invalid.', 500);
        }

        $webAuthn = $this->getWebAuthn($request);

        try {
            $webAuthn->processGet(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $publicKeyRaw,
                $challenge,
                null,
                true
            );
        } catch (\Throwable $verifyError) {
            \Log::error('PASSWORDLESS VERIFY ERROR', [
                'message' => $verifyError->getMessage(),
                'session_id' => $request->session()->getId(),
                'rp_id' => $this->getRpId($request),
                'origin_host' => $request->getHost(),
                'credential_id' => $credentialId,
                'user_code' => $userCode,
                'has_user_handle' => !empty($userHandle),
                'has_challenge' => !empty($challenge),
            ]);

            return $this->fail('Biometric verification failed: ' . $verifyError->getMessage(), 401);
        }

        DB::table('USER_BIO')
            ->where('ID', $bio->ID)
            ->update([
                'SIGN_COUNT' => max((int) $bio->SIGN_COUNT + 1, 1),
                'LAST_USED_AT' => now(),
                'IS_ACTIVE' => 'Y',
            ]);

        if (DB::getSchemaBuilder()->hasColumn('USERS', 'BIO_ENABLED')) {
            DB::table('USERS')
                ->where('USER_CODE', $userCode)
                ->update([
                    'BIO_ENABLED' => 'Y',
                ]);
        }

        $this->updateUserLoginStats($userCode, $request, true);

        $authModelClass = config('auth.providers.users.model');

        if (!$authModelClass || !class_exists($authModelClass)) {
            return $this->fail('Auth model is not configured properly.', 500);
        }

        $authUser = $authModelClass::where('USER_CODE', $userCode)->first();

        if (!$authUser) {
            return $this->fail('Auth user model not found for this account.', 404);
        }

        Auth::guard('web')->login($authUser);
        $request->session()->regenerate();

        Session::put('auth.user', $this->normalizeUserResponse($user));
        Session::put('auth.user_code', $userCode);
        Session::put('auth.login_type', 'biometric');
        Session::put('auth.logged_in_at', now()->toDateTimeString());

        Session::forget('user_bio.passwordless.challenge');

        return $this->success([
            'user' => $this->normalizeUserResponse($user),
            'login_type' => 'biometric',
        ], 'Biometric login successful.');
    } catch (\Throwable $e) {
        \Log::error('PASSWORDLESS LOGIN FATAL', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $this->fail($e->getMessage(), 500);
    }
}



    protected function normalizeCreateArgsForJson($args): array
    {
        $arr = json_decode(json_encode($args), true);

        if (!is_array($arr)) {
            throw new \RuntimeException('Unable to normalize WebAuthn create args.');
        }

        if (array_key_exists('challenge', $arr)) {
            $arr['challenge'] = $this->bytesToB64Url($arr['challenge']);
        }

        if (isset($arr['user']) && array_key_exists('id', $arr['user'])) {
            $arr['user']['id'] = $this->bytesToB64Url($arr['user']['id']);
        }

        if (!empty($arr['excludeCredentials']) && is_array($arr['excludeCredentials'])) {
            foreach ($arr['excludeCredentials'] as $i => $cred) {
                if (array_key_exists('id', $cred)) {
                    $arr['excludeCredentials'][$i]['id'] = $this->bytesToB64Url($cred['id']);
                }
            }
        }

        return $arr;
    }

    protected function normalizeGetArgsForJson($args): array
    {
        $arr = json_decode(json_encode($args), true);

        if (!is_array($arr)) {
            throw new \RuntimeException('Unable to normalize WebAuthn get args.');
        }

        if (array_key_exists('challenge', $arr)) {
            $arr['challenge'] = $this->bytesToB64Url($arr['challenge']);
        }

        if (!empty($arr['allowCredentials']) && is_array($arr['allowCredentials'])) {
            foreach ($arr['allowCredentials'] as $i => $cred) {
                if (array_key_exists('id', $cred)) {
                    $arr['allowCredentials'][$i]['id'] = $this->bytesToB64Url($cred['id']);
                }
            }
        }

        return $arr;
    }

    protected function success($data = [], string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function fail(string $message = 'Request failed', int $code = 400, $data = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function findUser(string $userCode)
    {
        return DB::table('USERS')
            ->where('USER_CODE', $userCode)
            ->first();
    }

    protected function activeCredentialsByUser(string $userCode)
    {
        return DB::table('USER_BIO')
            ->where('USER_CODE', $userCode)
            ->where('IS_ACTIVE', 'Y')
            ->orderByDesc('ID')
            ->get();
    }

    protected function activeCredentialByCredentialId(string $credentialId)
    {
        return DB::table('USER_BIO')
            ->where('CREDENTIAL_ID', $credentialId)
            ->where('IS_ACTIVE', 'Y')
            ->first();
    }

    protected function updateUserLoginStats(string $userCode, Request $request, bool $bioLogin = false): void
    {
        $user = $this->findUser($userCode);

        if (!$user) {
            return;
        }

        $payload = [
            'LAST_LOGIN_AT' => now(),
            'LAST_LOGIN_IP' => $request->ip(),
            'LAST_BROWSER' => substr((string) $request->userAgent(), 0, 150),
            'LOGIN_COUNT' => (int) ($user->LOGIN_COUNT ?? 0) + 1,
            'LAST_SEEN_AT' => now(),
            'LOGIN_STAT' => 1,
        ];

        if ($bioLogin && DB::getSchemaBuilder()->hasColumn('USERS', 'BIO_ENABLED')) {
            $payload['BIO_ENABLED'] = 'Y';
        }

        DB::table('USERS')
            ->where('USER_CODE', $userCode)
            ->update($payload);
    }

    protected function normalizeUserResponse($user): array
    {
        return [
            'USER_CODE'   => $user->USER_CODE ?? null,
            'USER_NAME'   => $user->USER_NAME ?? null,
            'EMAIL_ADD'   => $user->EMAIL_ADD ?? null,
            'USER_TYPE'   => $user->USER_TYPE ?? null,
            'BRANCH_CODE' => $user->BRANCH_CODE ?? null,
            'RC_CODE'     => $user->RC_CODE ?? null,
            'ACTIVE'      => $user->ACTIVE ?? null,
            'POSITION'    => $user->POSITION ?? null,
        ];
    }

    /**
     * -------------------------------------------------------------
     * 1) REGISTRATION OPTIONS
     * -------------------------------------------------------------
     */
    public function registerOptions(Request $request)
    {
        try {
            $request->validate([
                'userCode' => 'required|string|max:25',
            ]);

            $userCode = trim((string) $request->userCode);
            $user = $this->findUser($userCode);

            if (!$user) {
                return $this->fail('User not found.', 404);
            }

            if (($user->ACTIVE ?? 'Y') !== 'Y') {
                return $this->fail('User is inactive.', 403);
            }

            $webAuthn = $this->getWebAuthn($request);

            $userId = (string) $userCode;
            $userName = (string) ($user->USER_CODE ?? $userCode);
            $userDisplayName = (string) ($user->USER_NAME ?? $userCode);

            $excludeCredentialIds = [];
            $existing = $this->activeCredentialsByUser($userCode);

            foreach ($existing as $row) {
                $decoded = $this->b64urlDecode($row->CREDENTIAL_ID);
                if ($decoded !== false && $decoded !== null) {
                    $excludeCredentialIds[] = $decoded;
                }
            }

            $createArgs = $webAuthn->getCreateArgs(
                $userId,
                $userName,
                $userDisplayName,
                60 * 4,
                true,
                'required',
                null
            );

            if (!empty($excludeCredentialIds)) {
                $createArgs->excludeCredentials = collect($excludeCredentialIds)
                    ->map(function ($id) {
                        return [
                            'type' => 'public-key',
                            'id' => $id,
                        ];
                    })
                    ->values()
                    ->all();
            }

            Session::put('user_bio.register.challenge', $webAuthn->getChallenge());
            Session::put('user_bio.register.user_code', $userCode);
            Session::put('user_bio.register.user_id', $userId);

            $payload = $this->normalizeCreateArgsForJson($createArgs);

            return $this->success($payload, 'Registration options generated.');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * -------------------------------------------------------------
     * 2) REGISTRATION VERIFY
     * -------------------------------------------------------------
     */
    public function registerVerify(Request $request)
    {
        try {
            $request->validate([
                'userCode' => 'required|string|max:25',
                'credential.id' => 'required|string',
                'credential.response.clientDataJSON' => 'required|string',
                'credential.response.attestationObject' => 'required|string',
            ]);

            $userCode = trim((string) $request->userCode);
            $sessionUserCode = Session::get('user_bio.register.user_code');
            $challenge = Session::get('user_bio.register.challenge');

            if (!$challenge || !$sessionUserCode || $sessionUserCode !== $userCode) {
                return $this->fail('Registration session expired or invalid.', 419);
            }

            $user = $this->findUser($userCode);
            if (!$user) {
                return $this->fail('User not found.', 404);
            }

            $webAuthn = $this->getWebAuthn($request);

            $clientDataJSON = $this->b64urlDecode($request->input('credential.response.clientDataJSON'));
            $attestationObject = $this->b64urlDecode($request->input('credential.response.attestationObject'));

            $result = $webAuthn->processCreate(
                $clientDataJSON,
                $attestationObject,
                $challenge,
                true,
                true,
                false
            );

            $credentialIdRaw = $result->credentialId ?? null;
            $publicKeyRaw = $result->credentialPublicKey ?? null;
            $signatureCounter = (int) ($result->signatureCounter ?? 0);

            if (!$credentialIdRaw || !$publicKeyRaw) {
                return $this->fail('Invalid biometric registration result.');
            }

            $credentialId = $this->b64urlEncode($credentialIdRaw);
            $publicKey = base64_encode($publicKeyRaw);

            $existingCredential = DB::table('USER_BIO')
                ->where('CREDENTIAL_ID', $credentialId)
                ->first();

            if ($existingCredential) {
                DB::table('USER_BIO')
                    ->where('ID', $existingCredential->ID)
                    ->update([
                        'USER_CODE' => $userCode,
                        'PUBLIC_KEY' => $publicKey,
                        'SIGN_COUNT' => $signatureCounter,
                        'IS_ACTIVE' => 'Y',
                        'LAST_USED_AT' => null,
                    ]);
            } else {
                DB::table('USER_BIO')->insert([
                    'USER_CODE' => $userCode,
                    'CREDENTIAL_ID' => $credentialId,
                    'PUBLIC_KEY' => $publicKey,
                    'SIGN_COUNT' => $signatureCounter,
                    'IS_ACTIVE' => 'Y',
                    'DATE_ADDED' => now(),
                    'LAST_USED_AT' => null,
                ]);
            }

            if (DB::getSchemaBuilder()->hasColumn('USERS', 'BIO_ENABLED')) {
                DB::table('USERS')
                    ->where('USER_CODE', $userCode)
                    ->update([
                        'BIO_ENABLED' => 'Y',
                    ]);
            }

            Session::forget('user_bio.register.challenge');
            Session::forget('user_bio.register.user_code');
            Session::forget('user_bio.register.user_id');

            return $this->success([
                'userCode' => $userCode,
                'credentialId' => $credentialId,
                'signCount' => $signatureCounter,
            ], 'Biometric credential registered successfully.');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * -------------------------------------------------------------
     * 3) LOGIN OPTIONS
     * -------------------------------------------------------------
     */
    public function loginOptions(Request $request)
    {
        try {
            $request->validate([
                'userCode' => 'required|string|max:25',
            ]);

            $userCode = trim((string) $request->userCode);
            $user = $this->findUser($userCode);

            if (!$user) {
                return $this->fail('User not found.', 404);
            }

            if (($user->ACTIVE ?? 'Y') !== 'Y') {
                return $this->fail('User is inactive.', 403);
            }

            $credentials = $this->activeCredentialsByUser($userCode);

            if ($credentials->isEmpty()) {
                return $this->fail('No active biometric credentials found for this user.', 404);
            }

            $ids = [];
            foreach ($credentials as $row) {
                $decoded = $this->b64urlDecode($row->CREDENTIAL_ID);
                if ($decoded !== false && $decoded !== null) {
                    $ids[] = $decoded;
                }
            }

            if (empty($ids)) {
                return $this->fail('Stored biometric credentials are invalid.');
            }

            $webAuthn = $this->getWebAuthn($request);

            $getArgs = $webAuthn->getGetArgs(
                $ids,
                60 * 4,
                false,
                false,
                false,
                false,
                true,
                'required'
            );

            Session::put('user_bio.login.challenge', $webAuthn->getChallenge());
            Session::put('user_bio.login.user_code', $userCode);

            $payload = $this->normalizeGetArgsForJson($getArgs);

            return $this->success($payload, 'Login options generated.');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * -------------------------------------------------------------
     * 4) LOGIN VERIFY
     * -------------------------------------------------------------
     */
  public function loginVerify(Request $request)
{
    try {
        $request->validate([
            'userCode' => 'required|string|max:25',
            'credential.id' => 'required|string',
            'credential.response.clientDataJSON' => 'required|string',
            'credential.response.authenticatorData' => 'required|string',
            'credential.response.signature' => 'required|string',
        ]);

        $userCode = trim((string) $request->input('userCode'));
        $sessionUserCode = Session::get('user_bio.login.user_code');
        $challenge = Session::get('user_bio.login.challenge');

        if (!$challenge || !$sessionUserCode || $sessionUserCode !== $userCode) {
            return $this->fail('Login session expired or invalid.', 419);
        }

        $user = $this->findUser($userCode);
        if (!$user) {
            return $this->fail('User not found.', 404);
        }

        if (($user->ACTIVE ?? 'Y') !== 'Y') {
            return $this->fail('User is inactive.', 403);
        }

        $credentialId = trim((string) $request->input('credential.id'));
        $bio = $this->activeCredentialByCredentialId($credentialId);

        if (!$bio) {
            return $this->fail('Biometric credential not found.', 404);
        }

        if ((string) $bio->USER_CODE !== $userCode) {
            return $this->fail('Biometric credential does not belong to this user.', 403);
        }

        $webAuthn = $this->getWebAuthn($request);

        $clientDataJSON = $this->b64urlDecode($request->input('credential.response.clientDataJSON'));
        $authenticatorData = $this->b64urlDecode($request->input('credential.response.authenticatorData'));
        $signature = $this->b64urlDecode($request->input('credential.response.signature'));
        $publicKeyRaw = base64_decode((string) $bio->PUBLIC_KEY);

        if (!$publicKeyRaw) {
            return $this->fail('Stored public key is invalid.');
        }

        $webAuthn->processGet(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            $publicKeyRaw,
            $challenge,
            null,
            true
        );

        DB::table('USER_BIO')
            ->where('ID', $bio->ID)
            ->update([
                'SIGN_COUNT' => max((int) $bio->SIGN_COUNT + 1, 1),
                'LAST_USED_AT' => now(),
                'IS_ACTIVE' => 'Y',
            ]);

        if (DB::getSchemaBuilder()->hasColumn('USERS', 'BIO_ENABLED')) {
            DB::table('USERS')
                ->where('USER_CODE', $userCode)
                ->update([
                    'BIO_ENABLED' => 'Y',
                ]);
        }

        $this->updateUserLoginStats($userCode, $request, true);

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT: create the real Laravel authenticated session
        |--------------------------------------------------------------------------
        */
        $authModelClass = config('auth.providers.users.model');

        if (!$authModelClass || !class_exists($authModelClass)) {
            return $this->fail('Auth model is not configured properly.', 500);
        }

        $authUser = $authModelClass::where('USER_CODE', $userCode)->first();

        if (!$authUser) {
            return $this->fail('Auth user model not found for this account.', 404);
        }

        Auth::guard('web')->login($authUser);
        $request->session()->regenerate();

        /*
        |--------------------------------------------------------------------------
        | Keep your custom session values too, if other parts of the app use them
        |--------------------------------------------------------------------------
        */
        Session::put('auth.user', $this->normalizeUserResponse($user));
        Session::put('auth.user_code', $userCode);
        Session::put('auth.login_type', 'biometric');
        Session::put('auth.logged_in_at', now()->toDateTimeString());

        Session::forget('user_bio.login.challenge');
        Session::forget('user_bio.login.user_code');

        return $this->success([
            'user' => $this->normalizeUserResponse($user),
            'login_type' => 'biometric',
        ], 'Biometric login successful.');
    } catch (\Throwable $e) {
        return $this->fail($e->getMessage(), 500);
    }
}

    /**
     * -------------------------------------------------------------
     * 5) LIST USER BIOMETRICS
     * -------------------------------------------------------------
     */
    public function listByUser(string $userCode)
    {
        try {
            $rows = DB::table('USER_BIO')
                ->select([
                    'ID',
                    'USER_CODE',
                    'CREDENTIAL_ID',
                    'SIGN_COUNT',
                    'IS_ACTIVE',
                    'DATE_ADDED',
                    'LAST_USED_AT',
                ])
                ->where('USER_CODE', $userCode)
                ->orderByDesc('ID')
                ->get();

            return $this->success($rows, 'Biometric credentials retrieved.');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * -------------------------------------------------------------
     * 6) DEACTIVATE BIOMETRIC
     * -------------------------------------------------------------
     */
    public function deactivate(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
            ]);

            $bio = DB::table('USER_BIO')->where('ID', $request->id)->first();

            if (!$bio) {
                return $this->fail('Biometric credential not found.', 404);
            }

            DB::table('USER_BIO')
                ->where('ID', $bio->ID)
                ->update([
                    'IS_ACTIVE' => 'N',
                ]);

            $remaining = DB::table('USER_BIO')
                ->where('USER_CODE', $bio->USER_CODE)
                ->where('IS_ACTIVE', 'Y')
                ->count();

            if ((int) $remaining === 0 && DB::getSchemaBuilder()->hasColumn('USERS', 'BIO_ENABLED')) {
                DB::table('USERS')
                    ->where('USER_CODE', $bio->USER_CODE)
                    ->update([
                        'BIO_ENABLED' => 'N',
                    ]);
            }

            return $this->success([], 'Biometric credential deactivated.');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * -------------------------------------------------------------
     * 7) DELETE BIOMETRIC
     * -------------------------------------------------------------
     */
    public function delete(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
            ]);

            $bio = DB::table('USER_BIO')->where('ID', $request->id)->first();

            if (!$bio) {
                return $this->fail('Biometric credential not found.', 404);
            }

            DB::table('USER_BIO')->where('ID', $bio->ID)->delete();

            $remaining = DB::table('USER_BIO')
                ->where('USER_CODE', $bio->USER_CODE)
                ->where('IS_ACTIVE', 'Y')
                ->count();

            if ((int) $remaining === 0 && DB::getSchemaBuilder()->hasColumn('USERS', 'BIO_ENABLED')) {
                DB::table('USERS')
                    ->where('USER_CODE', $bio->USER_CODE)
                    ->update([
                        'BIO_ENABLED' => 'N',
                    ]);
            }

            return $this->success([], 'Biometric credential deleted.');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }
}