<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use hisorange\BrowserDetect\Parser as Browser;
use App\Support\TenantCatalog;
use App\Mail\AdminApprovalMail;
use App\Mail\AccountLockedMail;


class AuthController extends Controller
{
    /**
     * Return companies for the login dropdown (from tenants.csv).
     */
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

    /**
     * Optional: quick diagnostic to confirm the active tenant binding.
     */
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

    /**
     * Register a user INSIDE the current tenant DB.
     */
    // public function register(Request $req)
    // {
    //     $v = Validator::make($req->all(), [
    //         'USER_CODE' => ['required', 'string', 'max:10'],
    //         'USER_NAME' => ['required', 'string', 'max:100'],
    //         'EMAIL_ADD' => ['required', 'email', 'max:255'],
    //         'PASSWORD'  => ['required', 'string', 'min:6'],
    //     ]);

    //     if ($v->fails()) {
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => $v->errors()->first(),
    //         ], 422);
    //     }

    //     $userId   = trim($req->input('USER_CODE'));
    //     $username = trim($req->input('USER_NAME'));
    //     $email    = trim($req->input('EMAIL_ADD'));
    //     $password = $req->input('PASSWORD');

    //     try {
    //         $existsUsername = DB::table('USERS')->where('USER_NAME', $username)->exists();
    //         if ($existsUsername) {
    //             return response()->json([
    //                 'status'  => 'error',
    //                 'message' => 'Username already taken.',
    //             ], 409);
    //         }

    //         $existsEmail = DB::table('USERS')->where('EMAIL_ADD', $email)->exists();
    //         if ($existsEmail) {
    //             return response()->json([
    //                 'status'  => 'error',
    //                 'message' => 'Email already registered.',
    //             ], 409);
    //         }

    //         // USERS table must have an auto-increment PK column (e.g., id BIGINT IDENTITY) for Auth.
    //         DB::table('USERS')->insert([
    //             'USER_CODE' => $userId,
    //             'USER_NAME' => $username,
    //             'EMAIL_ADD' => $email,
    //             'PASSWORD'  => Hash::make($password),
    //         ]);

    //         $user = DB::table('USERS')
    //             ->select('USER_CODE', 'USER_NAME', 'EMAIL_ADD')
    //             ->where('USER_NAME', $username)
    //             ->first();

    //         return response()->json([
    //             'status'  => 'success',
    //             'message' => 'Registration successful.',
    //             'data'    => $user,
    //             'tenant'  => [
    //                 'database' => $req->attributes->get('tenant.database'),
    //                 'code'     => $req->attributes->get('tenant.code'),
    //                 'company'  => $req->attributes->get('tenant.company'),
    //             ],
    //         ], 201);
    //     } catch (Throwable $e) {
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => 'Registration failed. ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // ORIGINAL REGISTER

    // public function register(Request $req)
    // {
    //     $v = Validator::make($req->all(), [
    //         'USER_CODE' => ['required', 'string', 'max:10'],
    //         'USER_NAME' => ['required', 'string', 'max:100'],
    //         'EMAIL_ADD' => ['required', 'email', 'max:255'],
    //         // ❌ NO PASSWORD
    //     ]);

    //     if ($v->fails()) {
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => $v->errors()->first(),
    //         ], 422);
    //     }

    //     $userId   = trim($req->input('USER_CODE'));
    //     $username = trim($req->input('USER_NAME'));
    //     $email    = trim($req->input('EMAIL_ADD'));

    //     try {
    //         // --------------------------------------------------
    //         // Uniqueness checks (per tenant DB)
    //         // --------------------------------------------------
    //         if (DB::table('users')->where('user_code', $userId)->exists()) {
    //             return response()->json([
    //                 'status'  => 'error',
    //                 'message' => 'User ID already exists.',
    //             ], 409);
    //         }

    //         if (DB::table('users')->where('email_add', $email)->exists()) {
    //             return response()->json([
    //                 'status'  => 'error',
    //                 'message' => 'Email already registered.',
    //             ], 409);
    //         }

    //         // --------------------------------------------------
    //         // Insert PENDING user (NO PASSWORD)
    //         // --------------------------------------------------
    //         DB::table('users')->insert([
    //             'user_code'    => $userId,
    //             'user_name'    => $username,
    //             'email_add'    => $email,
    //             'user_type'    => 'R',     // Regular user
    //             'active'       => 'P',     // ⬅ PENDING
    //             'view_costamt' => 'N',
    //             'edit_uprice'  => 'N',
    //             'password'     => null,    // ⬅ IMPORTANT
    //             'tpword_date'  => null,
    //             'cpword_date'  => null,
    //         ]);

    //         return response()->json([
    //             'status'  => 'success',
    //             'message' => 'Registration submitted. Awaiting admin approval.',
    //             'data'    => [
    //                 'USER_CODE' => $userId,
    //                 'USER_NAME' => $username,
    //                 'EMAIL_ADD' => $email,
    //             ],
    //         ], 201);
    //     } catch (Throwable $e) {
    //         report($e);
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => 'Registration failed. ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function register(Request $req)
    {
        $v = Validator::make($req->all(), [
            'USER_CODE' => ['required', 'string', 'max:10'],
            'USER_NAME' => ['required', 'string', 'max:100'],
            'EMAIL_ADD' => ['required', 'email', 'max:255'],
            // ❌ NO PASSWORD
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $v->errors()->first(),
            ], 422);
        }

        $userId   = trim($req->input('USER_CODE'));
        $username = trim($req->input('USER_NAME'));
        $email    = trim($req->input('EMAIL_ADD'));
        $company  = $req->header('X-Company-DB'); // Fetch the company name for the email

        try {
            // --------------------------------------------------
            // Uniqueness checks (per tenant DB)
            // --------------------------------------------------
            if (DB::table('users')->where('user_code', $userId)->exists()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User ID already exists.',
                ], 409);
            }

            if (DB::table('users')->where('email_add', $email)->exists()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Email already registered.',
                ], 409);
            }

            // --------------------------------------------------
            // Insert PENDING user (NO PASSWORD)
            // --------------------------------------------------
            DB::table('users')->insert([
                'user_code'    => $userId,
                'user_name'    => $username,
                'email_add'    => $email,
                'user_type'    => 'R',     // Regular user
                'active'       => 'P',     // ⬅ PENDING
                'view_costamt' => 'N',
                'edit_uprice'  => 'N',
                'password'     => null,    // ⬅ IMPORTANT
                'tpword_date'  => null,
                'cpword_date'  => null,
            ]);

            // ==================================================
            // NEW: SEND EMAIL NOTIFICATION TO SECURITY ADMINS
            // ==================================================
            $securityAdmins = DB::table('users')
                ->where('user_type', 'X')
                ->where('active', 'Y')
                ->get();

            $emailsSent = 0;
            foreach ($securityAdmins as $admin) {
                // Force all DB columns to lowercase to prevent SQL Server case sensitivity issues
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
            // ==================================================

            return response()->json([
                'status'  => 'success',
                'message' => 'Registration submitted. Awaiting admin approval.',
                'debug_emails_sent' => $emailsSent, // Optional: helpful to see if the loop actually found admins
                'data'    => [
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


    /**
     * Session (cookie) login + SingleSession (kick old session).
     * No tokens are returned—React uses the Sanctum session cookie.
     */
    // public function login(Request $req)
    // {
    //     $v = Validator::make($req->all(), [
    //         'USER_CODE' => ['required', 'string'],
    //         'PASSWORD'  => ['required', 'string'],
    //     ]);
    //     if ($v->fails()) {
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => $v->errors()->first(),
    //         ], 422);
    //     }

    //     $userCode = trim($req->input('USER_CODE'));
    //     $password = $req->input('PASSWORD');

    //     try {
    //         /** @var \App\Models\User|null $user */
    //         $user = User::where('USER_CODE', $userCode)->first();

    //         // Validate credentials against USERS.PASSWORD (bcrypt)
    //         if (!$user || !Hash::check($password, $user->PASSWORD)) {
    //             return response()->json([
    //                 'status'  => 'error',
    //                 'message' => 'Invalid credentials.',
    //             ], 401);
    //         }


    //         $xff = $req->headers->get('X-Forwarded-For');
    //         $ipAddress = $req->headers->get('CF-Connecting-IP')       // Cloudflare
    //             ?: ($xff ? trim(explode(',', $xff)[0]) : $req->ip()); // first hop in XFF or fallback
    //         $ipAddress = preg_replace('/^::ffff:/', '', trim($ipAddress)); // ::ffff:192.0.2.1 -> 192.0.2.1
    //         $ipAddress = substr($ipAddress, 0, 45); // fit IPv6 max length



    //         // Regenerate session to prevent fixation, then log in using web guard
    //         $req->session()->regenerate();
    //         Auth::login($user);

    //         // ---- SingleSession (cross-device) ----
    //         // Keep only one active session per user across all devices/browsers.
    //         $cacheKey = "user:active_session:{$user->id}";
    //         $current  = session()->getId();
    //         $oldId    = Cache::get($cacheKey);

    //         if ($oldId && $oldId !== $current) {
    //             // Kick the old session (destroy at the session handler level)
    //             Session::getHandler()->destroy($oldId);
    //         }

    //         // Map user → current session id. TTL tracks with session lifetime.
    //         Cache::put($cacheKey, $current, now()->addMinutes(config('session.lifetime')));


    //         try {
    //             $browserName = Browser::browserName();     
    //             $browserVer  = Browser::browserVersion();  
    //             $osName      = Browser::platformName();    
    //             $deviceType  = Browser::deviceType();      
    //             $browserInfo = "{$browserName} {$browserVer} on {$osName} ({$deviceType})";

    //         DB::connection('tenant')
    //             ->table('USERS')
    //             ->where('USER_CODE', $user->USER_CODE)
    //             ->update([
    //                 'LAST_LOGIN_AT' => now(), 
    //                 'LOGIN_STAT' => 1,                   
    //                 'LAST_LOGIN_IP' => $ipAddress,             
    //                 'LOGIN_COUNT'   => DB::raw('ISNULL(LOGIN_COUNT, 0) + 1'),
    //                 'LAST_BROWSER'  => $browserInfo, 
    //             ]);
    //         } catch (\Throwable $e) {
    //                 \Log::warning("Failed to update login audit for {$user->USER_CODE}: " . $e->getMessage());
    //         }


    //         $data = [
    //             'USER_CODE' => $user->USER_CODE,
    //             'USER_NAME' => $user->USER_NAME,
    //             'EMAIL_ADD' => $user->EMAIL_ADD,
    //         ];

    //         return response()->json([
    //             'status'  => 'success',
    //             'message' => 'Login successful.',
    //             'data'    => $data,
    //             // No token—session cookie is already set by Sanctum
    //             'tenant'  => [
    //                 'database' => $req->attributes->get('tenant.database'),
    //                 'code'     => $req->attributes->get('tenant.code'),
    //                 'company'  => $req->attributes->get('tenant.company'),
    //             ],
    //         ]);
    //     } catch (Throwable $e) {
    //         \Log::error("Login failed for user {$userCode}: " . $e->getMessage());
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => 'Login failed due to a server error. Please check database configuration.',
    //         ], 500);
    //     }
    // }



    public function login(Request $req, \App\Services\SeatGate $gate)
    {
        $v = Validator::make($req->all(), [
            'USER_CODE' => ['required', 'string'],
            'PASSWORD'  => ['required', 'string'],
        ]);
        if ($v->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $v->errors()->first(),
            ], 422);
        }

        $userCode = trim($req->input('USER_CODE'));
        $password = $req->input('PASSWORD');

        try {
            /** @var \App\Models\User|null $user */
            $user = User::where('USER_CODE', $userCode)->first();

            // ── Fetch maxLog from HS_SEC policy ──────────────────────────────
            $policy = null;
            $maxLog = 0;
            try {
                $policy = DB::connection('tenant')->table('HS_SEC')->first();
                $maxLog = (int) ($policy->maxLog ?? 0);
            } catch (\Throwable $e) {
                Log::warning("Could not read HS_SEC policy: " . $e->getMessage());
            }

            // 1) Credentials
            if (!$user || !Hash::check($password, $user->PASSWORD)) {

                // Track failed attempt if user exists and maxLog is enforced
                if ($user && $maxLog > 0) {
                    $newStat = (int)($user->STAT ?? 0) + 1;

                    DB::connection('tenant')
                        ->table('users')
                        ->where('user_code', $userCode)
                        ->update(['stat' => $newStat]);

                    // Lock account when threshold is reached
                    if ($newStat >= $maxLog) {
                        DB::connection('tenant')
                            ->table('users')
                            ->where('user_code', $userCode)
                            ->update(['active' => 'N']);

                        // ── Notify all Security Admins by email ───────────────
                        try {
                            $lockedUserName  = $user->USER_NAME  ?? $userCode;
                            $lockedUserEmail = $user->EMAIL_ADD  ?? '';
                            $company         = $req->header('X-Company-DB');

                            $secAdmins = DB::connection('tenant')
                                ->table('users')
                                ->where('user_type', 'X')
                                ->where('active', 'Y')
                                ->get();

                            foreach ($secAdmins as $admin) {
                                $adminArr   = array_change_key_case((array) $admin, CASE_LOWER);
                                $adminEmail = $adminArr['email_add'] ?? null;
                                $adminName  = $adminArr['user_name']  ?? 'Security Administrator';

                                if (!empty($adminEmail)) {
                                    Mail::to($adminEmail)->send(
                                        new AccountLockedMail(
                                            $adminName,
                                            $userCode,
                                            $lockedUserName,
                                            $lockedUserEmail,
                                            $maxLog,
                                            $company
                                        )
                                    );
                                }
                            }
                        } catch (\Throwable $mailEx) {
                            Log::warning("AccountLockedMail failed for {$userCode}: " . $mailEx->getMessage());
                        }
                        // ─────────────────────────────────────────────────────

                        return response()->json([
                            'status'  => 'error',
                            'code'    => 'LOCKED',
                            'message' => "Your account has been locked after {$maxLog} failed attempts. Please contact your administrator.",
                        ], 403);
                    }

                    $remaining = $maxLog - $newStat;
                    return response()->json([
                        'status'  => 'error',
                        'message' => "Invalid credentials. {$remaining} attempt(s) remaining before account lockout.",
                    ], 401);
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            // 2) Account status gate (ADD THIS)
            $active = strtoupper(trim((string)($user->ACTIVE ?? '')));

            if ($active === 'P') {
                return response()->json([
                    'status'  => 'error',
                    'code'    => 'PENDING',
                    'message' => 'Your account is pending administrator approval.',
                ], 403);
            }

            if ($active !== 'Y') {
                $stat = (int)($user->STAT ?? 0);

                if ($maxLog > 0 && $stat >= $maxLog) {
                    return response()->json([
                        'status'  => 'error',
                        'code'    => 'LOCKED',
                        'message' => "Your account has been locked after {$maxLog} failed login attempt(s). Please contact your administrator to release your account.",
                    ], 403);
                }

                return response()->json([
                    'status'  => 'error',
                    'code'    => 'INACTIVE',
                    'message' => 'Your account is inactive. Please contact your administrator.',
                ], 403);
            }

            // ── Password Expiry check (passExp) ─────────────────────────────────
            $passExp = (int) ($policy->passExp ?? 0);
            if ($passExp > 0) {
                $lastChanged = $user->CPWORD_DATE ?? $user->TPWORD_DATE ?? null;

                if ($lastChanged === null) {
                    return response()->json([
                        'status'  => 'error',
                        'code'    => 'PASSWORD_EXPIRED',
                        'message' => 'Your password has expired. Please set a new password.',
                    ], 403);
                }

                $daysSinceChange = now()->diffInDays(\Carbon\Carbon::parse($lastChanged));
                if ($daysSinceChange >= $passExp) {
                    return response()->json([
                        'status'  => 'error',
                        'code'    => 'PASSWORD_EXPIRED',
                        'message' => "Your password expired {$daysSinceChange} day(s) ago. Please set a new password.",
                    ], 403);
                }
            }
            // ────────────────────────────────────────────────────────────────────

            // 2) Seat enforcement (atomic inside SeatGate; uses USER_CODE + login_active)
            if (!$gate->tryOccupy($user->USER_CODE)) {
                return response()->json([
                    'status'  => 'error',
                    'code'    => 'SEAT_LIMIT',
                    'message' => 'Concurrent user limit reached. Please try again later.',
                ], 429);
            }


            // 3) IP (CF/XFF aware)
            $xff = $req->headers->get('X-Forwarded-For');
            $ipAddress = $req->headers->get('CF-Connecting-IP')       // Cloudflare
                ?: ($xff ? trim(explode(',', $xff)[0]) : $req->ip()); // first hop in XFF or fallback
            $ipAddress = preg_replace('/^::ffff:/', '', trim($ipAddress)); // IPv6-mapped IPv4
            $ipAddress = substr($ipAddress, 0, 45);

            // 4) Start session AFTER seat confirmed
            $req->session()->regenerate();
            Auth::login($user);

            // 5) Single-session (cross-device) using USER_CODE (keeps it consistent)
            $cacheKey = "user:active_session:{$user->USER_CODE}";
            $current  = session()->getId();
            $oldId    = Cache::get($cacheKey);

            if ($oldId && $oldId !== $current) {
                Session::getHandler()->destroy($oldId);
            }
            Cache::put($cacheKey, $current, now()->addMinutes(config('session.lifetime')));

            // 6) Audit fields (use your tenant connection if that’s your pattern)
            try {
                $browserName = \hisorange\BrowserDetect\Parser::browserName();
                $browserVer  = \hisorange\BrowserDetect\Parser::browserVersion();
                $osName      = \hisorange\BrowserDetect\Parser::platformName();
                $deviceType  = \hisorange\BrowserDetect\Parser::deviceType();
                $browserInfo = "{$browserName} {$browserVer} on {$osName} ({$deviceType})";

                DB::connection('tenant')
                    ->table('USERS')
                    ->where('USER_CODE', $user->USER_CODE)
                    ->update([
                        'LAST_LOGIN_AT' => now(),
                        'LOGIN_STAT'  => 1,                 // ← your final flag
                        'LAST_LOGIN_IP' => $ipAddress,
                        'LOGIN_COUNT'   => DB::raw('ISNULL(LOGIN_COUNT, 0) + 1'),
                        'LAST_BROWSER'  => $browserInfo,
                        'LAST_SEEN_AT'  => now(),             // keep inactivity in sync
                        'STAT'          => 0,                 // reset failed attempt counter
                    ]);
            } catch (\Throwable $e) {
                Log::warning("Failed to update login audit for {$user->USER_CODE}: " . $e->getMessage());
            }

            $data = [
                'USER_CODE' => $user->USER_CODE,
                'USER_NAME' => $user->USER_NAME,
                'EMAIL_ADD' => $user->EMAIL_ADD,
                'USER_TYPE' => $user->USER_TYPE,
            ];

            return response()->json([
                'status'  => 'success',
                'message' => 'Login successful.',
                'data'    => $data,
                'tenant'  => [
                    'database' => $req->attributes->get('tenant.database'),
                    'code'     => $req->attributes->get('tenant.code'),
                    'company'  => $req->attributes->get('tenant.company'),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("Login failed for user {$userCode}: " . $e->getMessage());
            // Important: if something failed after tryOccupy but before response AND user not logged in,
            // SeatGate didn't mark login_active unless it succeeded. No extra cleanup here.
            return response()->json([
                'status'  => 'error',
                'message' => 'Login failed due to a server error. Please check database configuration.',
            ], 500);
        }
    }




    /**
     * Return the currently authenticated user (for /api/me).
     */
    // public function me(Request $req)
    // {
    //     if (!Auth::check()) {
    //         return response()->json(['message' => 'Unauthenticated'], 401);
    //     }

    //     $u = Auth::user();
    //     return response()->json([
    //         'USER_CODE' => $u->USER_CODE,
    //         'USER_NAME' => $u->USER_NAME,
    //         'EMAIL_ADD' => $u->EMAIL_ADD,
    //     ]);
    // }


    public function me(Request $req)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $u = Auth::user();

        $cacheKey = "user:active_session:{$u->USER_CODE}";
        $currentSessionId = $req->session()->getId();
        $mappedSessionId = Cache::get($cacheKey);

        $loginStat = DB::connection('tenant')
            ->table('USERS')
            ->where('USER_CODE', $u->USER_CODE)
            ->value('LOGIN_STAT');

        if ((string)$loginStat !== '1' || ($mappedSessionId && $mappedSessionId !== $currentSessionId)) {
            Auth::logout();
            $req->session()->invalidate();
            $req->session()->regenerateToken();

            return response()->json(['message' => 'Session expired'], 401);
        }

        return response()->json([
            'USER_CODE' => $u->USER_CODE,
            'USER_NAME' => $u->USER_NAME,
            'EMAIL_ADD' => $u->EMAIL_ADD,
            'USER_TYPE' => $u->USER_TYPE,
        ]);
    }


    public function heartbeat(Request $req)
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false], 401);
        }
        // keep the session ‘fresh’ (optional)
        $req->session()->put('_last_activity', time());
        return response()->json(['ok' => true]);
    }

    /**
     * Logout: clear SingleSession mapping (if this session owns it) and invalidate the session.
     * NOTE: Exclude SingleSession middleware on this route so logout always works.
     */
    // public function logout(Request $req)
    // {
    //     if (Auth::check()) {
    //         $user = Auth::user();
    //         $cacheKey = "user:active_session:{$user->USER_CODE}";
    //         $current  = session()->getId();
    //         $mapped   = Cache::get($cacheKey);

    //         if ($mapped === $current) {
    //             Cache::forget($cacheKey);
    //         }


    //         try {
    //             DB::connection('tenant')
    //                 ->table('USERS')
    //                 ->where('USER_CODE', $user->USER_CODE)
    //                 ->update([
    //                     'LOGIN_STAT' => 0
    //                 ]);
    //         } catch (\Throwable $e) {
    //             Log::warning("Failed to update logout audit for {$user->USER_CODE}: " . $e->getMessage());
    //         }

    //         Auth::logout();
    //     }

    //     // Invalidate + new CSRF token
    //     $req->session()->invalidate();
    //     $req->session()->regenerateToken();

    //     return response()->json(['ok' => true, 'message' => 'Successfully logged out']);
    // }

    public function logout(Request $req)
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                $cacheKey = "user:active_session:{$user->USER_CODE}";
                $current  = $req->session()->getId();
                $mapped   = Cache::get($cacheKey);

                if ($mapped === $current) {
                    Cache::forget($cacheKey);
                }

                try {
                    DB::connection('tenant')
                        ->table('USERS')
                        ->where('USER_CODE', $user->USER_CODE)
                        ->update([
                            'LOGIN_STAT' => 0,
                        ]);
                } catch (\Throwable $e) {
                    Log::warning("Failed to update logout audit for {$user->USER_CODE}: " . $e->getMessage());
                }

                Auth::logout();
            }

            $req->session()->invalidate();
            $req->session()->regenerateToken();

            return response()->json([
                'ok' => true,
                'message' => 'Successfully logged out',
            ]);
        } catch (\Throwable $e) {
            Log::error("Logout failed: " . $e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => 'Logout failed.',
            ], 500);
        }
    }
}