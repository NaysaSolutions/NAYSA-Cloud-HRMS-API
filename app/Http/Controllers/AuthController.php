<?php

namespace App\Http\Controllers;

use App\Mail\AccountLockedMail;
use App\Mail\AdminApprovalMail;
use App\Models\User;
use App\Support\TenantCatalog;
use App\Support\LicenseCompanyCatalog;
use App\Services\FixedSystemAccounts;
use hisorange\BrowserDetect\Parser as Browser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    private int $staleMinutes = 5;

    private function tenantKey(Request $req): string
    {
        $key =
            $req->attributes->get('tenant.database')
            ?: $req->attributes->get('tenant.code')
            ?: $req->header('X-Company-DB')
            ?: 'default';

        return preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $key);
    }

    private function activeSessionKey(Request $req, string $userCode): string
    {
        return "tenant:{$this->tenantKey($req)}:user:active_session:{$userCode}";
    }

    private function legacyActiveSessionKey(string $userCode): string
    {
        return "user:active_session:{$userCode}";
    }

    private function approvalKey(Request $req, string $userCode): string
    {
        return "tenant:{$this->tenantKey($req)}:login_approval:{$userCode}";
    }

    private function legacyApprovalKey(string $userCode): string
    {
        return "login_approval:{$userCode}";
    }

    private function approvalStatusKey(string $requestId): string
    {
        return "login_approval_status:{$requestId}";
    }

    private function clientIp(Request $req): string
    {
        $xff = $req->headers->get('X-Forwarded-For');

        $ip = $req->headers->get('CF-Connecting-IP')
            ?: ($xff ? trim(explode(',', $xff)[0]) : $req->ip());

        $ip = preg_replace('/^::ffff:/', '', trim((string) $ip));

        return substr($ip, 0, 45);
    }

    private function browserInfo(): string
    {
        try {
            $browserName = Browser::browserName();
            $browserVer  = Browser::browserVersion();
            $osName      = Browser::platformName();
            $deviceType  = Browser::deviceType();

            return "{$browserName} {$browserVer} on {$osName} ({$deviceType})";
        } catch (Throwable $e) {
            return 'Unknown device';
        }
    }

    private function isStale($lastSeenAt): bool
    {
        if (empty($lastSeenAt)) {
            return true;
        }

        try {
            return \Carbon\Carbon::parse($lastSeenAt)->lt(now()->subMinutes($this->staleMinutes));
        } catch (Throwable $e) {
            return true;
        }
    }

    private function touchUserSession(Request $req, string $userCode, string $sessionId): void
    {
        $activeKey = $this->activeSessionKey($req, $userCode);

        Cache::put(
            $activeKey,
            $sessionId,
            now()->addMinutes((int) config('session.lifetime'))
        );

        Cache::forget($this->legacyActiveSessionKey($userCode));

        DB::connection('tenant')
            ->table('USERS')
            ->where('USER_CODE', $userCode)
            ->update([
                'LOGIN_STAT'  => 1,
                'LAST_SEEN_AT' => now(),
            ]);
    }

    private function releaseUserSession(Request $req, string $userCode, ?string $sessionId = null): void
    {
        $activeKey = $this->activeSessionKey($req, $userCode);
        $legacyKey = $this->legacyActiveSessionKey($userCode);

        if (!empty($sessionId)) {
            try {
                Session::getHandler()->destroy($sessionId);
            } catch (Throwable $e) {
                Log::warning("Failed to destroy session for {$userCode}: " . $e->getMessage());
            }
        }

        Cache::forget($activeKey);
        Cache::forget($legacyKey);
        Cache::forget($this->approvalKey($req, $userCode));
        Cache::forget($this->legacyApprovalKey($userCode));

        DB::connection('tenant')
            ->table('USERS')
            ->where('USER_CODE', $userCode)
            ->update([
                'LOGIN_STAT'  => 0,
                'LAST_SEEN_AT' => null,
            ]);
    }

    /**
     * Return the real login identity represented by the current session.
     *
     * Normal users use USERS.USER_CODE. Fixed accounts use the session-only
     * code HEARTSTRONG or MIRACLE even though Laravel authenticates through
     * a real template user.
     */
    private function currentIdentityCode(Request $req): string
    {
        if ((bool) $req->session()->get('SYSTEM_ACCOUNT', false)) {
            return strtoupper(trim((string) $req->session()->get(
                'SYSTEM_ACCOUNT_CODE',
                ''
            )));
        }

        $user = Auth::user();

        return strtoupper(trim((string) ($user->USER_CODE ?? '')));
    }

    /**
     * Keep a fixed system account's tenant-specific session mapping alive.
     * This deliberately does not update USERS.LOGIN_STAT or LAST_SEEN_AT.
     */
    private function touchSystemSession(
        Request $req,
        string $systemCode,
        string $sessionId
    ): void {
        Cache::put(
            $this->activeSessionKey($req, $systemCode),
            $sessionId,
            now()->addMinutes((int) config('session.lifetime'))
        );

        Cache::forget($this->legacyActiveSessionKey($systemCode));
    }

    /**
     * Release a fixed account session without changing the carrier/template
     * user's LOGIN_STAT, LAST_SEEN_AT, or license-seat occupancy.
     */
    private function releaseSystemSession(
        Request $req,
        string $systemCode,
        ?string $sessionId = null
    ): void {
        if (!empty($sessionId)) {
            try {
                Session::getHandler()->destroy($sessionId);
            } catch (Throwable $e) {
                Log::warning(
                    "Failed to destroy system-account session for {$systemCode}: "
                    . $e->getMessage()
                );
            }
        }

        Cache::forget($this->activeSessionKey($req, $systemCode));
        Cache::forget($this->legacyActiveSessionKey($systemCode));
        Cache::forget($this->approvalKey($req, $systemCode));
        Cache::forget($this->legacyApprovalKey($systemCode));
    }

    /**
     * Support both earlier and current FixedSystemAccounts return shapes.
     *
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    private function normalizeSystemIdentity(
        FixedSystemAccounts $fixedAccounts,
        string $requestedCode,
        array $identity
    ): array {
        $systemCode = $fixedAccounts->normalize(
            (string) ($identity['USER_CODE'] ?? $requestedCode)
        );

        $permissionUserCode = $fixedAccounts->normalize(
            (string) (
                $identity['PERMISSION_USER_CODE']
                ?? $identity['AUTH_USER_CODE']
                ?? 'NAYSA'
            )
        );

        $authUserCode = $fixedAccounts->normalize(
            (string) (
                $identity['AUTH_USER_CODE']
                ?? $permissionUserCode
            )
        );

        $defaultMode = $systemCode === 'HEARTSTRONG'
            ? 'LICENSE_ADMIN'
            : 'SYSTEM_ADMIN';

        return [
            ...$identity,
            'USER_CODE' => $systemCode,
            'USER_NAME' => trim((string) (
                $identity['USER_NAME'] ?? $systemCode
            )) ?: $systemCode,
            'EMAIL_ADD' => null,
            'AUTH_USER_CODE' => $authUserCode,
            'PERMISSION_USER_CODE' => $permissionUserCode,
            'ACCOUNT_MODE' => strtoupper(trim((string) (
                $identity['ACCOUNT_MODE'] ?? $defaultMode
            ))),
            'SYSTEM_ACCOUNT' => true,
            'LICENSE_EXEMPT' => true,
        ];
    }

    public function companies()
    {
        $catalog = new TenantCatalog();

        $list = array_map(function ($r) {
            return [
                'code'     => $r['code']     ?? '',
                'company'  => $r['company']  ?? '',
                'database' => $r['database'] ?? '',
            ];
        }, $catalog->all());

        return response()->json([
            'success' => true,
            'data'    => $list,
        ]);
    }

    public function licenseCompany()
    {
        $catalog = new LicenseCompanyCatalog();

        $list = array_map(function ($row) {
            return [
                'compCode' => $row['compCode'] ?? '',
                'compName' => $row['compName'] ?? '',
            ];
        }, $catalog->all());

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    public function updateLicenseCompany(Request $request)
    {
        $request->validate([
            'compCode' => ['required', 'string', 'max:50'],
            'compName' => ['required', 'string', 'max:255'],
        ]);

        $compCode = trim((string) $request->input('compCode'));
        $compName = trim((string) $request->input('compName'));

        $licensePath = storage_path('app/licenseCompany.json');
        $tenantsPath = storage_path('app/tenants.json');

        if (!file_exists($licensePath)) {
            return response()->json([
                'message' => 'licenseCompany.json not found.',
            ], 404);
        }

        if (!file_exists($tenantsPath)) {
            return response()->json([
                'message' => 'tenants.json not found.',
            ], 404);
        }

        try {
            $licenseCompanies = json_decode(
                file_get_contents($licensePath),
                true
            );

            if (!is_array($licenseCompanies)) {
                return response()->json([
                    'message' => 'Invalid licenseCompany.json format.',
                ], 422);
            }

            $licenseFound = false;

            foreach ($licenseCompanies as &$company) {
                if (($company['compCode'] ?? '') === $compCode) {
                    $company['compName'] = $compName;
                    $licenseFound = true;
                    break;
                }
            }

            unset($company);

            if (!$licenseFound) {
                return response()->json([
                    'message' =>
                        'Company code not found in licenseCompany.json.',
                ], 404);
            }

            $tenants = json_decode(
                file_get_contents($tenantsPath),
                true
            );

            if (!is_array($tenants)) {
                return response()->json([
                    'message' => 'Invalid tenants.json format.',
                ], 422);
            }

            $tenantRecord = null;
            $tenantFound = false;

            foreach ($tenants as &$tenant) {
                if (($tenant['code'] ?? '') === $compCode) {
                    $tenant['company'] = $compName;
                    $tenantRecord = $tenant;
                    $tenantFound = true;
                    break;
                }
            }

            unset($tenant);

            if (!$tenantFound) {
                if (count($tenants) === 0) {
                    return response()->json([
                        'message' =>
                            'tenants.json has no existing record to copy '
                            . 'connection settings from.',
                    ], 422);
                }

                $lastTenant = $tenants[count($tenants) - 1];

                $tenantRecord = [
                    'code' => $compCode,
                    'company' => $compName,
                    'database' => $compCode,
                    'host' => $lastTenant['host'] ?? '',
                    'port' => $lastTenant['port'] ?? '1433',
                    'username' => $lastTenant['username'] ?? '',
                    'password' => $lastTenant['password'] ?? '',
                ];

                $tenants[] = $tenantRecord;
            }

            file_put_contents(
                $licensePath,
                json_encode(
                    $licenseCompanies,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                ),
                LOCK_EX
            );

            file_put_contents(
                $tenantsPath,
                json_encode(
                    $tenants,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                ),
                LOCK_EX
            );

            return response()->json([
                'message' =>
                    'License company and tenant successfully updated.',
                'licenseCompany' => [
                    'compCode' => $compCode,
                    'compName' => $compName,
                ],
                'tenant' => $tenantRecord,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Failed to update license company: '
                . $e->getMessage()
            );

            return response()->json([
                'message' => 'Failed to update license company.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ping(Request $req)
    {
        return response()->json([
            'ok'      => true,
            'message' => 'Tenant connection active',
            'tenant'  => [
                'database' => $req->attributes->get('tenant.database'),
                'code'     => $req->attributes->get('tenant.code'),
                'company'  => $req->attributes->get('tenant.company'),
            ],
        ]);
    }

    public function register(Request $req)
    {
        $v = Validator::make($req->all(), [
            'USER_CODE' => ['required', 'string', 'max:10'],
            'USER_NAME' => ['required', 'string', 'max:100'],
            'EMAIL_ADD' => ['required', 'email', 'max:255'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $v->errors()->first(),
            ], 422);
        }

        $userId   = strtoupper(trim($req->input('USER_CODE')));
        $username = trim($req->input('USER_NAME'));
        $email    = trim($req->input('EMAIL_ADD'));
        $company  = $req->header('X-Company-DB');

        if (app(FixedSystemAccounts::class)->exists($userId)) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'This User ID is reserved as a fixed system account.',
            ], 422);
        }

        try {
            if (DB::connection('tenant')->table('USERS')->where('USER_CODE', $userId)->exists()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User ID already exists.',
                ], 409);
            }

            if (DB::connection('tenant')->table('USERS')->where('EMAIL_ADD', $email)->exists()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Email already registered.',
                ], 409);
            }

            DB::connection('tenant')->table('USERS')->insert([
                'USER_CODE'    => $userId,
                'USER_NAME'    => $username,
                'EMAIL_ADD'    => $email,
                'USER_TYPE'    => 'R',
                'ACTIVE'       => 'P',
                'VIEW_COSTAMT' => 'N',
                'EDIT_UPRICE'  => 'N',
                'PASSWORD'     => null,
                'TPWORD_DATE'  => null,
                'CPWORD_DATE'  => null,
                'STAT'         => 0,
                'LOGIN_STAT'   => 0,
            ]);

            $securityAdmins = DB::connection('tenant')
                ->table('USERS')
                ->where('USER_TYPE', 'X')
                ->where('ACTIVE', 'Y')
                ->get();

            $emailsSent = 0;

            foreach ($securityAdmins as $admin) {
                $adminArray = array_change_key_case((array) $admin, CASE_LOWER);

                $adminEmail = $adminArray['email_add'] ?? null;
                $adminName  = $adminArray['user_name'] ?? 'Security Administrator';

                if (!empty($adminEmail)) {
                    Mail::to($adminEmail)->send(
                        new AdminApprovalMail(
                            $adminName,
                            $userId,
                            $username,
                            $email,
                            $company
                        )
                    );

                    $emailsSent++;
                }
            }

            return response()->json([
                'status'            => 'success',
                'message'           => 'Registration submitted. Awaiting admin approval.',
                'debug_emails_sent' => $emailsSent,
                'data'              => [
                    'USER_CODE' => $userId,
                    'USER_NAME' => $username,
                    'EMAIL_ADD' => $email,
                ],
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status'  => 'error',
                'message' => 'Registration failed. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function login(
        Request $req,
        \App\Services\SeatGate $gate,
        FixedSystemAccounts $fixedAccounts
    ) {
        $validator = Validator::make($req->all(), [
            'USER_CODE' => ['required', 'string'],
            'PASSWORD' => ['required', 'string'],
            'forceLogin' => ['sometimes', 'boolean'],
            'approvalRequestId' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $userCode = $fixedAccounts->normalize(
            $req->input('USER_CODE')
        );
        $password = (string) $req->input('PASSWORD');

        try {
            $isSystemAccount = $fixedAccounts->exists($userCode);
            $systemIdentity = null;

            if ($isSystemAccount) {
                $authenticatedIdentity = $fixedAccounts->authenticate(
                    $userCode,
                    $password
                );

                if (!is_array($authenticatedIdentity)) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'INVALID_CREDENTIALS',
                        'message' => 'Invalid credentials.',
                    ], 401);
                }

                $systemIdentity = $this->normalizeSystemIdentity(
                    $fixedAccounts,
                    $userCode,
                    $authenticatedIdentity
                );

                $user = User::whereRaw(
                    'UPPER(LTRIM(RTRIM(USER_CODE))) = ?',
                    [$systemIdentity['AUTH_USER_CODE']]
                )->first();

                if (!$user) {
                    Log::error(
                        'Fixed system-account authentication template is missing.',
                        [
                            'systemAccount' =>
                                $systemIdentity['USER_CODE'],
                            'authUserCode' =>
                                $systemIdentity['AUTH_USER_CODE'],
                        ]
                    );

                    return response()->json([
                        'status' => 'error',
                        'code' => 'SYSTEM_ACCOUNT_TEMPLATE_MISSING',
                        'message' =>
                            'The authentication template account '
                            . $systemIdentity['AUTH_USER_CODE']
                            . ' does not exist in the selected company.',
                    ], 500);
                }
            } else {
                $user = User::whereRaw(
                    'UPPER(LTRIM(RTRIM(USER_CODE))) = ?',
                    [$userCode]
                )->first();
            }

            $policy = null;
            $maxLog = 0;

            if (!$isSystemAccount) {
                try {
                    $policy = DB::connection('tenant')
                        ->table('HS_SEC')
                        ->first();

                    $policyArray = $policy
                        ? array_change_key_case(
                            (array) $policy,
                            CASE_LOWER
                        )
                        : [];

                    $maxLog = (int) (
                        $policyArray['maxlog'] ?? 0
                    );
                } catch (Throwable $e) {
                    Log::warning(
                        'Could not read HS_SEC policy: '
                        . $e->getMessage()
                    );
                }

                if (
                    !$user
                    || !Hash::check(
                        $password,
                        (string) $user->PASSWORD
                    )
                ) {
                    if ($user && $maxLog > 0) {
                        $newStat =
                            (int) ($user->STAT ?? 0) + 1;

                        DB::connection('tenant')
                            ->table('USERS')
                            ->where('USER_CODE', $userCode)
                            ->update(['STAT' => $newStat]);

                        if ($newStat >= $maxLog) {
                            DB::connection('tenant')
                                ->table('USERS')
                                ->where('USER_CODE', $userCode)
                                ->update(['ACTIVE' => 'N']);

                            try {
                                $securityAdmins =
                                    DB::connection('tenant')
                                        ->table('USERS')
                                        ->where('USER_TYPE', 'X')
                                        ->where('ACTIVE', 'Y')
                                        ->get();

                                foreach ($securityAdmins as $admin) {
                                    $adminArray =
                                        array_change_key_case(
                                            (array) $admin,
                                            CASE_LOWER
                                        );

                                    $adminEmail =
                                        $adminArray['email_add'] ?? null;
                                    $adminName =
                                        $adminArray['user_name']
                                        ?? 'Security Administrator';

                                    if (!empty($adminEmail)) {
                                        Mail::to($adminEmail)->send(
                                            new AccountLockedMail(
                                                $adminName,
                                                $userCode,
                                                $user->USER_NAME
                                                    ?? $userCode,
                                                $user->EMAIL_ADD ?? '',
                                                $maxLog,
                                                $req->header(
                                                    'X-Company-DB'
                                                )
                                            )
                                        );
                                    }
                                }
                            } catch (Throwable $mailException) {
                                Log::warning(
                                    "AccountLockedMail failed for "
                                    . "{$userCode}: "
                                    . $mailException->getMessage()
                                );
                            }

                            return response()->json([
                                'status' => 'error',
                                'code' => 'LOCKED',
                                'message' =>
                                    "Your account has been locked after "
                                    . "{$maxLog} failed attempts. "
                                    . 'Please contact your administrator.',
                            ], 403);
                        }

                        $remaining = $maxLog - $newStat;

                        return response()->json([
                            'status' => 'error',
                            'message' =>
                                "Invalid credentials. {$remaining} "
                                . 'attempt(s) remaining before '
                                . 'account lockout.',
                        ], 401);
                    }

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid credentials.',
                    ], 401);
                }

                $active = strtoupper(trim(
                    (string) ($user->ACTIVE ?? '')
                ));

                if ($active === 'P') {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'PENDING',
                        'message' =>
                            'Your account is pending '
                            . 'administrator approval.',
                    ], 403);
                }

                if ($active !== 'Y') {
                    $stat = (int) ($user->STAT ?? 0);

                    if ($maxLog > 0 && $stat >= $maxLog) {
                        return response()->json([
                            'status' => 'error',
                            'code' => 'LOCKED',
                            'message' =>
                                "Your account has been locked after "
                                . "{$maxLog} failed login attempt(s). "
                                . 'Please contact your administrator '
                                . 'to release your account.',
                        ], 403);
                    }

                    return response()->json([
                        'status' => 'error',
                        'code' => 'INACTIVE',
                        'message' =>
                            'Your account is inactive. Please '
                            . 'contact your administrator.',
                    ], 403);
                }

                $policyArray = $policy
                    ? array_change_key_case(
                        (array) $policy,
                        CASE_LOWER
                    )
                    : [];

                $passwordExpiration = (int) (
                    $policyArray['passexp'] ?? 0
                );

                if ($passwordExpiration > 0) {
                    $lastChanged =
                        $user->CPWORD_DATE
                        ?? $user->TPWORD_DATE
                        ?? null;

                    if ($lastChanged === null) {
                        return response()->json([
                            'status' => 'error',
                            'code' => 'PASSWORD_EXPIRED',
                            'message' =>
                                'Your password has expired. '
                                . 'Please set a new password.',
                        ], 403);
                    }

                    $daysSinceChange = now()->diffInDays(
                        \Carbon\Carbon::parse($lastChanged)
                    );

                    if ($daysSinceChange >= $passwordExpiration) {
                        return response()->json([
                            'status' => 'error',
                            'code' => 'PASSWORD_EXPIRED',
                            'message' =>
                                "Your password expired "
                                . "{$daysSinceChange} day(s) ago. "
                                . 'Please set a new password.',
                        ], 403);
                    }
                }
            }

            $forceLogin = $req->boolean('forceLogin');
            $approvalRequestId = trim((string) $req->input(
                'approvalRequestId',
                ''
            ));

            $sessionIdentityCode = $isSystemAccount
                ? $systemIdentity['USER_CODE']
                : $user->USER_CODE;

            $activeKey = $this->activeSessionKey(
                $req,
                $sessionIdentityCode
            );

            $legacyKey = $this->legacyActiveSessionKey(
                $sessionIdentityCode
            );

            $oldSessionId =
                Cache::get($activeKey)
                ?: Cache::get($legacyKey);

            $currentBeforeLogin =
                $req->session()->getId();

            if (!$isSystemAccount) {
                $loginRow = DB::connection('tenant')
                    ->table('USERS')
                    ->where('USER_CODE', $user->USER_CODE)
                    ->select('LOGIN_STAT', 'LAST_SEEN_AT')
                    ->first();

                $loginStatus = (string) (
                    $loginRow->LOGIN_STAT ?? '0'
                );

                $isStale = $this->isStale(
                    $loginRow->LAST_SEEN_AT ?? null
                );

                if (
                    $oldSessionId
                    && $oldSessionId !== $currentBeforeLogin
                    && ($loginStatus !== '1' || $isStale)
                ) {
                    $this->releaseUserSession(
                        $req,
                        $user->USER_CODE,
                        $oldSessionId
                    );

                    $oldSessionId = null;
                }

                if ($loginStatus === '1' && $isStale) {
                    $this->releaseUserSession(
                        $req,
                        $user->USER_CODE,
                        $oldSessionId ?: null
                    );

                    $oldSessionId = null;
                }
            }

            $approvedByCurrentSession = false;

            if ($approvalRequestId !== '' && !$forceLogin) {
                $approvalStatus = Cache::get(
                    $this->approvalStatusKey(
                        $approvalRequestId
                    )
                );

                if (!$approvalStatus) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'LOGIN_APPROVAL_EXPIRED',
                        'message' =>
                            'Login approval request has '
                            . 'expired. Please try again.',
                    ], 403);
                }

                if (
                    ($approvalStatus['status'] ?? '')
                    === 'denied'
                ) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'LOGIN_DENIED',
                        'message' =>
                            'Login request was denied by '
                            . 'the active session.',
                    ], 403);
                }

                if (
                    ($approvalStatus['status'] ?? '')
                        === 'approved'
                    && ($approvalStatus['userCode'] ?? '')
                        === $sessionIdentityCode
                ) {
                    $approvedByCurrentSession = true;
                    $forceLogin = true;
                }
            }

            if ($forceLogin || $approvedByCurrentSession) {
                $sessionToDestroy =
                    $oldSessionId
                    && $oldSessionId !== $currentBeforeLogin
                        ? $oldSessionId
                        : null;

                if ($isSystemAccount) {
                    $this->releaseSystemSession(
                        $req,
                        $sessionIdentityCode,
                        $sessionToDestroy
                    );
                } else {
                    $this->releaseUserSession(
                        $req,
                        $sessionIdentityCode,
                        $sessionToDestroy
                    );
                }

                if ($approvalRequestId !== '') {
                    Cache::forget(
                        $this->approvalStatusKey(
                            $approvalRequestId
                        )
                    );
                }

                $oldSessionId = null;
            }

            if (
                $oldSessionId
                && $oldSessionId !== $currentBeforeLogin
                && !$forceLogin
                && !$approvedByCurrentSession
            ) {
                $requestId = (string) Str::uuid();

                Cache::put(
                    $this->approvalKey(
                        $req,
                        $sessionIdentityCode
                    ),
                    [
                        'requestId' => $requestId,
                        'userCode' => $sessionIdentityCode,
                        'ipAddress' => $this->clientIp($req),
                        'browserInfo' => $this->browserInfo(),
                        'requestedAt' =>
                            now()->toDateTimeString(),
                    ],
                    now()->addSeconds(60)
                );

                Cache::put(
                    $this->approvalStatusKey($requestId),
                    [
                        'status' => 'pending',
                        'userCode' => $sessionIdentityCode,
                    ],
                    now()->addSeconds(90)
                );

                return response()->json([
                    'status' => 'error',
                    'code' => 'LOGIN_APPROVAL_REQUIRED',
                    'requestId' => $requestId,
                    'message' =>
                        'This account is already logged in. '
                        . 'Waiting for approval from the '
                        . 'active session.',
                ], 409);
            }

            if (
                !$isSystemAccount
                && !$gate->tryOccupy($user->USER_CODE)
            ) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SEAT_LIMIT',
                    'message' =>
                        'Concurrent user limit reached. '
                        . 'Please try again later.',
                ], 429);
            }

            $ipAddress = $this->clientIp($req);

            Auth::login($user);
            $req->session()->regenerate();

            $currentSessionId =
                $req->session()->getId();

            Cache::put(
                $activeKey,
                $currentSessionId,
                now()->addMinutes(
                    (int) config('session.lifetime')
                )
            );

            Cache::forget($legacyKey);
            Cache::forget(
                $this->legacyApprovalKey(
                    $sessionIdentityCode
                )
            );

            if ($approvalRequestId !== '') {
                Cache::forget(
                    $this->approvalStatusKey(
                        $approvalRequestId
                    )
                );
            }

            $req->session()->put(
                '_last_activity',
                time()
            );

            if ($isSystemAccount) {
                $req->session()->put([
                    'SYSTEM_ACCOUNT' => true,
                    'SYSTEM_ACCOUNT_CODE' =>
                        $systemIdentity['USER_CODE'],
                    'SYSTEM_ACCOUNT_NAME' =>
                        $systemIdentity['USER_NAME'],
                    'AUTH_USER_CODE' =>
                        $systemIdentity['AUTH_USER_CODE'],
                    'PERMISSION_USER_CODE' =>
                        $systemIdentity['PERMISSION_USER_CODE'],
                    'ACCOUNT_MODE' =>
                        $systemIdentity['ACCOUNT_MODE'],
                    'LICENSE_EXEMPT' => true,
                ]);

                Log::info(
                    'Fixed system account logged in.',
                    [
                        'systemAccount' =>
                            $systemIdentity['USER_CODE'],
                        'accountMode' =>
                            $systemIdentity['ACCOUNT_MODE'],
                        'authUserCode' =>
                            $systemIdentity['AUTH_USER_CODE'],
                        'permissionUserCode' =>
                            $systemIdentity['PERMISSION_USER_CODE'],
                        'tenant' => $this->tenantKey($req),
                        'ipAddress' => $ipAddress,
                    ]
                );
            } else {
                $req->session()->forget([
                    'SYSTEM_ACCOUNT',
                    'SYSTEM_ACCOUNT_CODE',
                    'SYSTEM_ACCOUNT_NAME',
                    'AUTH_USER_CODE',
                    'PERMISSION_USER_CODE',
                    'ACCOUNT_MODE',
                    'LICENSE_EXEMPT',
                ]);

                DB::connection('tenant')
                    ->table('USERS')
                    ->where('USER_CODE', $user->USER_CODE)
                    ->update([
                        'LAST_LOGIN_AT' => now(),
                        'LOGIN_STAT' => 1,
                        'LAST_LOGIN_IP' => $ipAddress,
                        'LOGIN_COUNT' => DB::raw(
                            'ISNULL(LOGIN_COUNT, 0) + 1'
                        ),
                        'LAST_BROWSER' => $this->browserInfo(),
                        'LAST_SEEN_AT' => now(),
                        'STAT' => 0,
                    ]);
            }

            $data = $isSystemAccount
                ? [
                    'USER_CODE' =>
                        $systemIdentity['USER_CODE'],
                    'USER_NAME' =>
                        $systemIdentity['USER_NAME'],
                    'EMAIL_ADD' => null,
                    'USER_TYPE' =>
                        $systemIdentity['ACCOUNT_MODE']
                            === 'LICENSE_ADMIN'
                                ? 'L'
                                : $user->USER_TYPE,
                    'AUTH_USER_CODE' =>
                        $systemIdentity['AUTH_USER_CODE'],
                    'PERMISSION_USER_CODE' =>
                        $systemIdentity['PERMISSION_USER_CODE'],
                    'ACCOUNT_MODE' =>
                        $systemIdentity['ACCOUNT_MODE'],
                    'SYSTEM_ACCOUNT' => true,
                    'LICENSE_EXEMPT' => true,
                ]
                : [
                    'USER_CODE' => $user->USER_CODE,
                    'USER_NAME' => $user->USER_NAME,
                    'EMAIL_ADD' => $user->EMAIL_ADD,
                    'USER_TYPE' => $user->USER_TYPE,
                    'PERMISSION_USER_CODE' =>
                        $user->USER_CODE,
                    'ACCOUNT_MODE' => 'NORMAL',
                    'SYSTEM_ACCOUNT' => false,
                    'LICENSE_EXEMPT' => false,
                ];

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful.',
                'data' => $data,
                'tenant' => [
                    'database' =>
                        $req->attributes->get(
                            'tenant.database'
                        ),
                    'code' =>
                        $req->attributes->get(
                            'tenant.code'
                        ),
                    'company' =>
                        $req->attributes->get(
                            'tenant.company'
                        ),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error(
                "Login failed for user {$userCode}: "
                . $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'tenant' => $this->tenantKey($req),
                ]
            );

            return response()->json([
                'status' => 'error',
                'message' =>
                    'Login failed due to a server error. '
                    . 'Please check database configuration.',
            ], 500);
        }
    }

    public function me(Request $req)
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user = Auth::user();
        $isSystemAccount = (bool) $req
            ->session()
            ->get('SYSTEM_ACCOUNT', false);

        if ($isSystemAccount) {
            $systemCode = $this->currentIdentityCode($req);
            $currentSessionId =
                $req->session()->getId();

            $mappedSessionId =
                Cache::get(
                    $this->activeSessionKey(
                        $req,
                        $systemCode
                    )
                )
                ?: Cache::get(
                    $this->legacyActiveSessionKey(
                        $systemCode
                    )
                );

            if (
                $systemCode === ''
                || (
                    $mappedSessionId
                    && $mappedSessionId !== $currentSessionId
                )
            ) {
                Auth::guard('web')->logout();
                $req->session()->invalidate();
                $req->session()->regenerateToken();

                return response()->json([
                    'status' => 'error',
                    'code' => 'SESSION_EXPIRED',
                    'message' => 'Session expired.',
                ], 401);
            }

            $this->touchSystemSession(
                $req,
                $systemCode,
                $currentSessionId
            );

            $req->session()->put(
                '_last_activity',
                time()
            );

            $accountMode = strtoupper(trim(
                (string) $req->session()->get(
                    'ACCOUNT_MODE',
                    'SYSTEM_ADMIN'
                )
            ));

            return response()->json([
                'USER_CODE' => $systemCode,
                'USER_NAME' =>
                    $req->session()->get(
                        'SYSTEM_ACCOUNT_NAME',
                        $systemCode
                    ),
                'EMAIL_ADD' => null,
                'USER_TYPE' =>
                    $accountMode === 'LICENSE_ADMIN'
                        ? 'L'
                        : $user->USER_TYPE,
                'AUTH_USER_CODE' =>
                    $req->session()->get(
                        'AUTH_USER_CODE',
                        $user->USER_CODE
                    ),
                'PERMISSION_USER_CODE' =>
                    $req->session()->get(
                        'PERMISSION_USER_CODE',
                        $user->USER_CODE
                    ),
                'ACCOUNT_MODE' => $accountMode,
                'SYSTEM_ACCOUNT' => true,
                'LICENSE_EXEMPT' => true,
            ]);
        }

        $currentSessionId =
            $req->session()->getId();

        $mappedSessionId =
            Cache::get(
                $this->activeSessionKey(
                    $req,
                    $user->USER_CODE
                )
            )
            ?: Cache::get(
                $this->legacyActiveSessionKey(
                    $user->USER_CODE
                )
            );

        $loginStatus = DB::connection('tenant')
            ->table('USERS')
            ->where('USER_CODE', $user->USER_CODE)
            ->value('LOGIN_STAT');

        $loggedInElsewhere =
            $mappedSessionId
            && $mappedSessionId !== $currentSessionId;

        $sessionExpired =
            (string) $loginStatus !== '1';

        if ($sessionExpired || $loggedInElsewhere) {
            Auth::guard('web')->logout();
            $req->session()->invalidate();
            $req->session()->regenerateToken();

            return response()->json([
                'status' => 'error',
                'code' => $loggedInElsewhere
                    ? 'LOGGED_IN_ELSEWHERE'
                    : 'SESSION_EXPIRED',
                'message' => $loggedInElsewhere
                    ? 'Your session ended because this '
                        . 'account was logged in from '
                        . 'another device.'
                    : 'Your session has expired. '
                        . 'Please login again.',
            ], 401);
        }

        $this->touchUserSession(
            $req,
            $user->USER_CODE,
            $currentSessionId
        );

        return response()->json([
            'USER_CODE' => $user->USER_CODE,
            'USER_NAME' => $user->USER_NAME,
            'EMAIL_ADD' => $user->EMAIL_ADD,
            'USER_TYPE' => $user->USER_TYPE,
            'PERMISSION_USER_CODE' => $user->USER_CODE,
            'ACCOUNT_MODE' => 'NORMAL',
            'SYSTEM_ACCOUNT' => false,
            'LICENSE_EXEMPT' => false,
        ]);
    }

    public function pendingLoginRequest(Request $req)
    {
        if (!Auth::check()) {
            return response()->json([
                'hasPending' => false,
            ], 401);
        }

        $identityCode = $this->currentIdentityCode($req);

        if ($identityCode === '') {
            return response()->json([
                'hasPending' => false,
            ], 401);
        }

        $pending =
            Cache::get(
                $this->approvalKey(
                    $req,
                    $identityCode
                )
            )
            ?: Cache::get(
                $this->legacyApprovalKey(
                    $identityCode
                )
            );

        if (!$pending) {
            return response()->json([
                'hasPending' => false,
            ]);
        }

        return response()->json([
            'hasPending' => true,
            'requestId' =>
                $pending['requestId'] ?? '',
            'ipAddress' =>
                $pending['ipAddress'] ?? '',
            'browserInfo' =>
                $pending['browserInfo'] ?? 'Unknown device',
            'requestedAt' =>
                $pending['requestedAt'] ?? '',
        ]);
    }

    public function approveLoginRequest(Request $req)
    {
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $req->validate([
            'requestId' => ['required', 'string'],
        ]);

        $identityCode = $this->currentIdentityCode($req);
        $requestId = (string) $req->input('requestId');

        $approvalKey = $this->approvalKey(
            $req,
            $identityCode
        );

        $legacyApprovalKey =
            $this->legacyApprovalKey(
                $identityCode
            );

        $pending =
            Cache::get($approvalKey)
            ?: Cache::get($legacyApprovalKey);

        if (
            !$pending
            || ($pending['requestId'] ?? '') !== $requestId
        ) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Login request not found or '
                    . 'already expired.',
            ], 404);
        }

        Cache::put(
            $this->approvalStatusKey($requestId),
            [
                'status' => 'approved',
                'userCode' => $identityCode,
            ],
            now()->addSeconds(90)
        );

        Cache::forget($approvalKey);
        Cache::forget($legacyApprovalKey);

        return response()->json([
            'status' => 'success',
            'message' => 'Login request approved.',
        ]);
    }

    public function denyLoginRequest(Request $req)
    {
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $req->validate([
            'requestId' => ['required', 'string'],
        ]);

        $identityCode = $this->currentIdentityCode($req);
        $requestId = (string) $req->input('requestId');

        $approvalKey = $this->approvalKey(
            $req,
            $identityCode
        );

        $legacyApprovalKey =
            $this->legacyApprovalKey(
                $identityCode
            );

        $pending =
            Cache::get($approvalKey)
            ?: Cache::get($legacyApprovalKey);

        if (
            !$pending
            || ($pending['requestId'] ?? '') !== $requestId
        ) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Login request not found or '
                    . 'already expired.',
            ], 404);
        }

        Cache::put(
            $this->approvalStatusKey($requestId),
            [
                'status' => 'denied',
                'userCode' => $identityCode,
            ],
            now()->addSeconds(90)
        );

        Cache::forget($approvalKey);
        Cache::forget($legacyApprovalKey);

        return response()->json([
            'status' => 'success',
            'message' => 'Login request denied.',
        ]);
    }

    public function loginRequestStatus($requestId)
    {
        $status = Cache::get($this->approvalStatusKey($requestId));

        if (!$status) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json([
            'status' => $status['status'] ?? 'pending',
        ]);
    }

    public function heartbeat(Request $req)
    {
        if (!Auth::check()) {
            return response()->json([
                'ok' => false,
            ], 401);
        }

        $user = Auth::user();
        $currentSessionId =
            $req->session()->getId();

        $isSystemAccount = (bool) $req
            ->session()
            ->get('SYSTEM_ACCOUNT', false);

        if ($isSystemAccount) {
            $systemCode =
                $this->currentIdentityCode($req);

            $mappedSessionId =
                Cache::get(
                    $this->activeSessionKey(
                        $req,
                        $systemCode
                    )
                )
                ?: Cache::get(
                    $this->legacyActiveSessionKey(
                        $systemCode
                    )
                );

            if (
                $systemCode === ''
                || (
                    $mappedSessionId
                    && $mappedSessionId !== $currentSessionId
                )
            ) {
                return response()->json([
                    'ok' => false,
                    'code' => 'LOGGED_IN_ELSEWHERE',
                    'message' =>
                        'This session is no longer active.',
                ], 401);
            }

            $this->touchSystemSession(
                $req,
                $systemCode,
                $currentSessionId
            );

            $req->session()->put(
                '_last_activity',
                time()
            );

            return response()->json([
                'ok' => true,
                'systemAccount' => true,
            ]);
        }

        $mappedSessionId =
            Cache::get(
                $this->activeSessionKey(
                    $req,
                    $user->USER_CODE
                )
            )
            ?: Cache::get(
                $this->legacyActiveSessionKey(
                    $user->USER_CODE
                )
            );

        $loginStatus = DB::connection('tenant')
            ->table('USERS')
            ->where('USER_CODE', $user->USER_CODE)
            ->value('LOGIN_STAT');

        if (
            $mappedSessionId
            && $mappedSessionId !== $currentSessionId
        ) {
            return response()->json([
                'ok' => false,
                'code' => 'LOGGED_IN_ELSEWHERE',
                'message' =>
                    'This session is no longer active.',
            ], 401);
        }

        if ((string) $loginStatus !== '1') {
            return response()->json([
                'ok' => false,
                'code' => 'SESSION_EXPIRED',
                'message' => 'Session expired.',
            ], 401);
        }

        $this->touchUserSession(
            $req,
            $user->USER_CODE,
            $currentSessionId
        );

        $req->session()->put(
            '_last_activity',
            time()
        );

        return response()->json([
            'ok' => true,
        ]);
    }

    public function logout(Request $req)
    {
        try {
            $isSystemAccount = (bool) $req
                ->session()
                ->get('SYSTEM_ACCOUNT', false);

            if ($isSystemAccount) {
                $systemCode =
                    $this->currentIdentityCode($req);

                if ($systemCode !== '') {
                    $currentSessionId =
                        $req->session()->getId();

                    $mappedSessionId =
                        Cache::get(
                            $this->activeSessionKey(
                                $req,
                                $systemCode
                            )
                        );

                    $legacyMappedSessionId =
                        Cache::get(
                            $this->legacyActiveSessionKey(
                                $systemCode
                            )
                        );

                    $ownsSession =
                        (
                            empty($mappedSessionId)
                            && empty($legacyMappedSessionId)
                        )
                        || $mappedSessionId === $currentSessionId
                        || $legacyMappedSessionId
                            === $currentSessionId;

                    if ($ownsSession) {
                        $this->releaseSystemSession(
                            $req,
                            $systemCode
                        );
                    }
                }

                if (Auth::check()) {
                    Auth::guard('web')->logout();
                }
            } elseif (Auth::check()) {
                $user = Auth::user();
                $currentSessionId =
                    $req->session()->getId();

                $activeKey =
                    $this->activeSessionKey(
                        $req,
                        $user->USER_CODE
                    );

                $legacyKey =
                    $this->legacyActiveSessionKey(
                        $user->USER_CODE
                    );

                $mappedSessionId =
                    Cache::get($activeKey);

                $legacyMappedSessionId =
                    Cache::get($legacyKey);

                $ownsSession =
                    (
                        empty($mappedSessionId)
                        && empty($legacyMappedSessionId)
                    )
                    || $mappedSessionId === $currentSessionId
                    || $legacyMappedSessionId
                        === $currentSessionId;

                if ($ownsSession) {
                    Cache::forget($activeKey);
                    Cache::forget($legacyKey);
                    Cache::forget(
                        $this->approvalKey(
                            $req,
                            $user->USER_CODE
                        )
                    );
                    Cache::forget(
                        $this->legacyApprovalKey(
                            $user->USER_CODE
                        )
                    );

                    DB::connection('tenant')
                        ->table('USERS')
                        ->where(
                            'USER_CODE',
                            $user->USER_CODE
                        )
                        ->update([
                            'LOGIN_STAT' => 0,
                            'LAST_SEEN_AT' => null,
                        ]);
                }

                Auth::guard('web')->logout();
            }

            $req->session()->invalidate();
            $req->session()->regenerateToken();

            return response()->json([
                'ok' => true,
                'message' =>
                    'Successfully logged out',
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Logout failed: '
                . $e->getMessage()
            );

            return response()->json([
                'ok' => false,
                'message' => 'Logout failed.',
            ], 500);
        }
    }

}