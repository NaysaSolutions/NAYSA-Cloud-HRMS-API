<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\TempPasswordMail;
use App\Mail\AdminApprovalMail;
use Throwable;

class UserController extends Controller
{
    /* ============================================================
     * GET / LOOKUP
     * ============================================================
     */

    public function get(Request $request)
    {
        $request->validate([
            'USER_CODE' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_Users @mode = ?, @params = ?',
                ['Get', $request->input('USER_CODE')]
            );

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function lookup()
    {
        try {
            $results = DB::select('EXEC sproc_PHP_USERS @mode = ?', ['Lookup']);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ============================================================
     * APPROVE / ADMIN ADD
     * ============================================================
     *
     * mode:
     * - admin_add → generate TEMP password
     * - release   → approve self-register (NO temp)
     */

    public function approveAccount(Request $req)
    {
        $req->validate([
            'userCode' => 'required|string',
            'mode'     => 'required|string|in:admin_add,release',
        ]);

        $userCode = trim($req->input('userCode'));
        $mode     = $req->input('mode');
        $company  = $req->header('X-Company-DB');

        try {
            $row  = collect(DB::select(
                "exec sproc_PHP_Users ?, ?",
                ['Get', $userCode]
            ))->first();

            $user = ($row && isset($row->result))
                ? (json_decode($row->result, true)[0] ?? null)
                : null;

            if (!$user || empty($user['emailAdd'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not found or email missing.'
                ], 422);
            }

            /* ======================================================
         * ADMIN ADD → TEMP PASSWORD + ACTIVATE
         * ====================================================== */
            if ($mode === 'admin_add') {

                $temp = substr(
                    str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'),
                    0,
                    8
                );

                $hash = Hash::make($temp);

                DB::statement(
                    "exec sproc_PHP_Users ?, ?",
                    [
                        'SetTempPassword',
                        json_encode([
                            'json_data' => [
                                'userCode'     => $userCode,
                                'passwordHash' => $hash,
                                'doneBy'       => $req->input('doneBy') ?? 'system',
                            ]
                        ])
                    ]
                );

                Mail::to($user['emailAdd'])->send(
                    new TempPasswordMail(
                        'admin_add',
                        $user['userName'] ?? $userCode,
                        $userCode,
                        $temp,
                        $company
                    )
                );

                return response()->json([
                    'status'  => 'success',
                    'message' => 'User activated. Temporary password sent.'
                ]);
            }

            /* ======================================================
         * RELEASE → APPROVE SELF-REGISTER (SET ACTIVE = Y)
         * ====================================================== */

            DB::statement(
                "exec sproc_PHP_Users ?, ?",
                [
                    'Upsert',
                    json_encode([
                        'json_data' => [
                            'userCode'      => $userCode,
                            'userName'      => $user['userName'] ?? '',
                            'userType'      => $user['userType'] ?? 'R',
                            'branchCode'    => $user['branchCode'] ?? '',
                            'rcCode'        => $user['rcCode'] ?? '',
                            'viewCostamt'   => $user['viewCostamt'] ?? 'N',
                            'editUprice'    => $user['editUprice'] ?? 'N',
                            'emailAdd'      => $user['emailAdd'],
                            'position'      => $user['position'] ?? '',
                            'active'        => 'Y',
                            'accountAction' => 'release',
                            'doneBy'        => $req->input('doneBy') ?? 'system',
                        ]
                    ])
                ]
            );

            Mail::to($user['emailAdd'])->send(
                new TempPasswordMail(
                    'release',
                    $user['userName'] ?? $userCode,
                    $userCode,
                    null,
                    $company
                )
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Account approved. Password setup link sent.'
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Approve failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    /* ============================================================
     * RELEASE LOCKED ACCOUNT
     * ============================================================
     *
     * Called when a Security Admin unlocks an account that was
     * locked after exceeding the maximum failed login attempts.
     * Resets stat + active via ReleaseAccount sproc, then emails
     * the user a password-reset link (purpose = 'unlock').
     */

    public function releaseLockedAccount(Request $req)
    {
        $req->validate([
            'userCode' => 'required|string',
        ]);

        $userCode = trim($req->input('userCode'));
        $doneBy   = $req->input('doneBy') ?? 'system';
        $company  = $req->header('X-Company-DB');

        try {
            // 1) Fetch user directly from the table.
            //    Avoids the extra JSON encode/decode round-trip of sproc Get,
            //    and guarantees we always find the user regardless of active status.
            $rawUser = DB::connection('tenant')
                ->table('users')
                ->where('user_code', $userCode)
                ->first();

            if (!$rawUser) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not found.',
                ], 422);
            }

            $userArr  = array_change_key_case((array) $rawUser, CASE_LOWER);
            $emailAdd = $userArr['email_add'] ?? null;
            $userName = $userArr['user_name'] ?? $userCode;

            if (empty($emailAdd)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User email is missing.',
                ], 422);
            }

            // 2) Release: reset active = Y and stat = 0
            //    Use DB::select instead of DB::statement so SQL Server errors are not silently swallowed.
            DB::select(
                "exec sproc_PHP_Users ?, ?",
                [
                    'ReleaseAccount',
                    json_encode([
                        'json_data' => [
                            'userCode' => $userCode,
                            'doneBy'   => $doneBy,
                        ]
                    ])
                ]
            );

            // 3) Email the user: account unlocked + password reset link
            Mail::to($emailAdd)->send(
                new TempPasswordMail(
                    'unlock',
                    $userName,
                    $userCode,
                    null,
                    $company
                )
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Account released. Password reset link sent to user.',
            ]);

        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Release failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    /* ============================================================
     * RESET PASSWORD (EMAIL LINK ONLY)
     * ============================================================
     */

    public function requestPasswordReset(Request $req)
    {
        $req->validate(['userCode' => 'required|string']);
        $userCode = trim($req->input('userCode'));
        $company  = $req->header('X-Company-DB');

        try {
            $row  = collect(DB::select("exec sproc_PHP_Users ?, ?", ['Get', $userCode]))->first();
            $user = ($row && isset($row->result)) ? (json_decode($row->result, true)[0] ?? null) : null;

            if (!$user || empty($user['emailAdd'])) {
                return response()->json(['status' => 'error', 'message' => 'User not found or email missing.'], 422);
            }

            Mail::to($user['emailAdd'])->send(new TempPasswordMail(
                'reset',
                $user['userName'] ?? $userCode,
                $userCode,
                null,
                $company
            ));

            return response()->json(['status' => 'success', 'message' => 'Password reset link sent.']);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['status' => 'error', 'message' => 'Reset failed: ' . $e->getMessage()], 500);
        }
    }

    /* ============================================================
     * CHANGE PASSWORD
     * ============================================================
     */

public function changePassword(Request $req)
{
    $mode = $req->input('mode');

    // -- Fetch HS_SEC policy first (needed for both validation rules & history check) --
    $policy    = DB::table('HS_SEC')->first();
    $policyArr = $policy ? array_change_key_case((array) $policy, CASE_LOWER) : [];

    $minimChar = (int) ($policyArr['minimchar'] ?? 0);
    $upLow     = (bool) ($policyArr['uplow']    ?? false);
    $letNum    = (bool) ($policyArr['letnum']   ?? false);
    $specChar  = (bool) ($policyArr['specchar'] ?? false);
    $passHis   = (int) ($policyArr['passhis']   ?? 0);

    // -- Build newPassword rules dynamically from policy ----------------------
    $passwordRules = ['required', 'string'];

    if ($minimChar > 0) {
        $passwordRules[] = "min:{$minimChar}";
    }

    if ($upLow) {
        $passwordRules[] = 'regex:/[A-Z]/';
        $passwordRules[] = 'regex:/[a-z]/';
    }

    if ($letNum) {
        $passwordRules[] = 'regex:/\d/';
    }

    if ($specChar) {
        $passwordRules[] = 'regex:/[^A-Za-z0-9]/';
    }

    $passwordRules[] = 'not_regex:/\s/'; // no spaces � always enforced

    $rules = [
        'userCode'    => 'required|string',
        'newPassword' => $passwordRules,
    ];

    if (!in_array($mode, ['reset', 'release', 'unlock', 'expired'], true)) {
        $rules['oldPassword'] = 'required|string';
    }

    $validated = $req->validate($rules);
    $userCode  = trim($validated['userCode']);

    $row = DB::table('users')->select('password')->where('user_code', $userCode)->first();
    if (!$row) {
        return response()->json(['status' => 'error', 'message' => 'User not found.'], 422);
    }

    if (!in_array($mode, ['reset', 'release', 'unlock', 'expired'], true)) {
        $rowArr = array_change_key_case((array) $row, CASE_LOWER);
        if (!Hash::check($validated['oldPassword'], $rowArr['password'])) {
            return response()->json(['status' => 'error', 'message' => 'Old password is incorrect.'], 422);
        }
    }

    // -- Password history check ------------------------------------------------
    if ($passHis > 0) {
        $history = DB::table('HS_PASSHIS')
            ->where('user_code', $userCode)
            ->where('changed_at', '>=', now()->subDays($passHis))
            ->orderByDesc('changed_at')
            ->get()
            ->map(fn($r) => array_change_key_case((array) $r, CASE_LOWER))
            ->pluck('pass_hash');

        foreach ($history as $oldHash) {
            if (Hash::check($validated['newPassword'], $oldHash)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "You cannot reuse a password used within the last {$passHis} day(s).",
                ], 422);
            }
        }
    } else {
        // passHis = 0 ? only block reuse of the exact current password
        $rowArr = array_change_key_case((array) $row, CASE_LOWER);
        if (Hash::check($validated['newPassword'], $rowArr['password'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'New password must differ from old password.',
            ], 422);
        }
    }
    // -------------------------------------------------------------------------

    $hash = Hash::make($validated['newPassword']);

    DB::select("exec sproc_PHP_Users ?, ?", [
        'SetChangedPassword',
        json_encode(['json_data' => [
            'userCode'     => $userCode,
            'passwordHash' => $hash,
            'doneBy'       => $req->input('doneBy') ?? $userCode,
        ]]),
    ]);

    return response()->json([
        'status'  => 'success',
        'message' => 'Password updated successfully.',
    ]);
}
    /* ============================================================
     * LOAD / UPSERT
     * ============================================================
     */

    public function load(Request $request)
    {
        $base     = $request->query('Status', 'Active');
        $userType = strtoupper($request->query('UserType', 'ALL'));
        $params   = $userType === 'ALL' ? "{$base}All" : "{$base}:{$userType}";

        $rows = DB::select(
            'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
            ['Load', $params]
        );

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function upsert(Request $request)
    {
        $json = $request->getContent();
        if (!$json && $request->all()) {
            $json = json_encode($request->all(), JSON_UNESCAPED_UNICODE);
        }

        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['success' => false, 'message' => 'Invalid JSON body.'], 422);
        }

        $rows = DB::select(
            'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
            ['Upsert', $json]
        );

        $result     = $rows[0] ?? null;
        $errorcount = (int) ($result->errorcount ?? 0);
        $errormsg   = $result->errormsg ?? '';

        if ($errorcount > 0) {
            // Sproc returned a validation error (e.g. missing required fields)
            return response()->json([
                'success'    => false,
                'message'    => $errormsg,
                'errorcount' => $errorcount,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'User saved successfully.',
        ]);
    }





    public function lookupAll(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_Users @mode = ?',
                ['LookupAll']
            );

            return response()->json([
                'success' => true,
                'data' => $results, // no more `json_encode`
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     * DELETE / REJECT USER
     * ============================================================
     */
    public function delete(Request $request)
    {
        $request->validate([
            'userCode' => 'required|string',
        ]);

        $userCode = trim($request->input('userCode'));
        $company  = $request->header('X-Company-DB');

        try {
            // 1. FETCH USER BEFORE DELETION TO GET THEIR EMAIL
            $userData = DB::select("SELECT * FROM users WHERE user_code = ?", [$userCode]);

            $isPending = false;
            $userEmail = null;
            $userName  = 'User';

            if (!empty($userData)) {
                // Force keys to lowercase for SQL Server case sensitivity
                $userArray = array_change_key_case((array) $userData[0], CASE_LOWER);

                if (($userArray['active'] ?? '') === 'P') {
                    $isPending = true;
                    $userEmail = $userArray['email_add'] ?? null;
                    $userName  = $userArray['user_name'] ?? 'User';
                }
            }

            // 2. EXECUTE THE DELETION STORED PROCEDURE
            $params = json_encode([
                'json_data' => [
                    'userCode' => $userCode,
                    'doneBy'   => $request->input('doneBy') ?? 'system',
                ]
            ], JSON_UNESCAPED_UNICODE);

            $rows = DB::select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['Delete', $params]
            );

            $rawResult = $rows[0]->result ?? null;
            $parsedResult = $rawResult ? json_decode($rawResult, true) : [];

            // 3. IF THE DELETED USER WAS PENDING, SEND THE REJECTION EMAIL USING THE REUSED BLADE
            if ($isPending && !empty($userEmail)) {
                try {
                    // Note the final parameter `true` which triggers the rejection view!
                    Mail::to($userEmail)->send(
                        new \App\Mail\AdminApprovalMail($userName, null, null, null, $company, true)
                    );
                } catch (\Throwable $mailError) {
                    Log::error("Failed to send rejection email to {$userEmail}: " . $mailError->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => $parsedResult['message'] ?? 'User delete processed successfully.',
                'deleteMode' => $parsedResult['deleteMode'] ?? null,
                'data' => $rows,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    /* ============================================================
     * CHECK DUPLICATE
     * ============================================================
     */
    public function checkDuplicate(Request $request)
    {
        // Safely extract userCode whether React sends it directly or inside json_data
        $userCode = $request->input('json_data.userCode') ?? $request->input('userCode');

        if (!$userCode) {
            return response()->json([
                'success' => false,
                'message' => 'User ID is required.'
            ], 400);
        }

        try {
            // The SQL procedure automatically wraps this string into JSON
            $results = DB::select(
                'EXEC sproc_PHP_Users @mode = ?, @params = ?',
                ['CheckDuplicate', trim($userCode)]
            );

            return response()->json([
                'success' => true,
                'data' => $results
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /* ============================================================
     * CHECK IN USED (To see if user has transaction history)
     * ============================================================
     */
    public function checkInUsed(Request $request)
    {
        // Safely extract userCode whether React sends it directly or inside json_data
        $userCode = $request->input('json_data.userCode') ?? $request->input('userCode');

        if (!$userCode) {
            return response()->json([
                'success' => false,
                'message' => 'User ID is required.'
            ], 400);
        }

        try {
            // The SQL procedure automatically wraps this string into JSON for 'CheckInUsed'
            $results = DB::select(
                'EXEC sproc_PHP_Users @mode = ?, @params = ?',
                ['CheckInUsed', trim($userCode)]
            );

            return response()->json([
                'success' => true,
                'data' => $results
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }



    /* ============================================================
     * LOGIN / PASSWORD POLICY (HS_SEC)
     * ============================================================
     */

    public function getPolicy()
    {
        try {
            $rows = DB::select(
                'EXEC dbo.sproc_PHP_Users @mode = ?',
                ['GetPolicy']
            );

            $raw  = $rows[0]->result ?? null;

            if (!$raw) {
                return response()->json(['success' => false, 'message' => 'No result returned from sproc.'], 500);
            }

            $data = json_decode($raw, true);

            // Normalise BIT (0/1) → boolean for the frontend
            $data['upLow']    = (bool) ($data['upLow']    ?? false);
            $data['letNum']   = (bool) ($data['letNum']   ?? false);
            $data['specChar'] = (bool) ($data['specChar'] ?? false);

            return response()->json(['success' => true, 'data' => $data], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function upsertPolicy(Request $request)
    {
        $request->validate([
            'minimChar' => 'required|integer|min:0',
            'passExp'   => 'required|integer|min:0',
            'passHis'   => 'required|integer|min:0',
            'upLow'     => 'required|boolean',
            'letNum'    => 'required|boolean',
            'specChar'  => 'required|boolean',
            'maxLog'    => 'required|integer|min:0',
        ]);

        try {
            $params = json_encode([
                'json_data' => [
                    'minimChar' => (int)  $request->input('minimChar'),
                    'passExp'   => (int)  $request->input('passExp'),
                    'passHis'   => (int)  $request->input('passHis'),
                    'upLow'     => $request->boolean('upLow')    ? 1 : 0,
                    'letNum'    => $request->boolean('letNum')   ? 1 : 0,
                    'specChar'  => $request->boolean('specChar') ? 1 : 0,
                    'maxLog'    => (int)  $request->input('maxLog'),
                    'doneBy'    => $request->input('doneBy') ?? 'system',
                ]
            ], JSON_UNESCAPED_UNICODE);

            $rows = DB::select(
                'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
                ['UpsertPolicy', $params]
            );

            $raw    = $rows[0]->result ?? null;
            $result = $raw ? json_decode($raw, true) : [];

            if (($result['status'] ?? '') === 'success') {
                return response()->json(['success' => true, 'message' => $result['message']], 200);
            }

            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Failed to save policy.'], 422);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    //     // User Profile Image
    //  public function uploadProfileImage(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'USER_CODE' => 'required|string',
    //         'PROFILE_IMAGE' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'Validation failed.',
    //             'errors' => $validator->errors(),
    //         ], 422);
    //     }

    //     try {
    //         $file = $request->file('PROFILE_IMAGE');

    //         if (!$file || !$file->isValid()) {
    //             return response()->json([
    //                 'message' => 'Invalid uploaded file.',
    //             ], 422);
    //         }

    //         $binary = file_get_contents($file->getRealPath());
    //         $hex = bin2hex($binary); // ✅ convert to HEX
    //         $mime = $file->getMimeType();

    //         \Log::info('UPLOAD PROFILE IMAGE START', [
    //             'userCode' => $request->USER_CODE,
    //             'mime' => $mime,
    //             'size' => strlen($binary),
    //         ]);

    //         DB::connection('tenant')->update(
    //             "UPDATE USERS
    //              SET PROFILE_IMG = CONVERT(VARBINARY(MAX), ?, 2),
    //                  PROFILE_IMG_MIME = ?
    //              WHERE USER_CODE = ?",
    //             [$hex, $mime, $request->USER_CODE]
    //         );

    //         \Log::info('UPLOAD PROFILE IMAGE SUCCESS', [
    //             'userCode' => $request->USER_CODE,
    //         ]);

    //         return response()->json([
    //             'message' => 'Profile image uploaded successfully.',
    //         ]);
    //     } catch (\Throwable $e) {
    //         \Log::error('UPLOAD PROFILE IMAGE ERROR', [
    //             'userCode' => $request->USER_CODE,
    //             'message' => $e->getMessage(),
    //         ]);

    //         return response()->json([
    //             'message' => 'Failed to upload profile image.',
    //         ], 500);
    //     }
    // }




    // public function getProfileImage($userCode)
    // {
    //     try {
    //         $row = DB::connection('tenant')->selectOne("
    //             SELECT PROFILE_IMG, PROFILE_IMG_MIME
    //             FROM USERS
    //             WHERE USER_CODE = ?
    //         ", [$userCode]);

    //         if (!$row || !$row->PROFILE_IMG) {
    //             return response()->json([
    //                 'message' => 'No image found.'
    //             ], 404);
    //         }

    //         $image = $row->PROFILE_IMG;

    //         if (is_resource($image)) {
    //             $image = stream_get_contents($image);
    //         }

    //         // If SQL Server returns 0xHEX...
    //         if (is_string($image) && strncmp($image, '0x', 2) === 0) {
    //             $decoded = hex2bin(substr($image, 2));
    //             if ($decoded !== false) {
    //                 $image = $decoded;
    //             }
    //         }

    //         // If SQL Server returns plain HEX without 0x
    //         if (
    //             is_string($image) &&
    //             preg_match('/^[0-9A-Fa-f]+$/', $image) &&
    //             strlen($image) % 2 === 0
    //         ) {
    //             $decoded = hex2bin($image);
    //             if ($decoded !== false) {
    //                 $image = $decoded;
    //             }
    //         }

    //         if ($image === false || $image === null || $image === '') {
    //             return response()->json([
    //                 'message' => 'Invalid image data.'
    //             ], 500);
    //         }

    //         \Log::info('PROFILE IMAGE DEBUG', [
    //             'userCode' => $userCode,
    //             'raw_type' => gettype($row->PROFILE_IMG),
    //             'mime' => $row->PROFILE_IMG_MIME,
    //             'final_len' => is_string($image) ? strlen($image) : null,
    //             'final_first_8_hex' => is_string($image)
    //                 ? strtoupper(bin2hex(substr($image, 0, 8)))
    //                 : null,
    //         ]);

    //         while (ob_get_level()) {
    //             ob_end_clean();
    //         }

    //         return response()->stream(function () use ($image) {
    //             echo $image;
    //         }, 200, [
    //             'Content-Type' => $row->PROFILE_IMG_MIME ?: 'image/jpeg',
    //             'Content-Length' => strlen($image),
    //             'Content-Disposition' => 'inline; filename="profile.jpg"',
    //             'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    //             'Pragma' => 'no-cache',
    //         ]);
    //     } catch (\Throwable $e) {
    //         \Log::error('PROFILE IMAGE ERROR', [
    //             'userCode' => $userCode,
    //             'error' => $e->getMessage(),
    //         ]);

    //         return response()->json([
    //             'message' => 'Failed to fetch profile image.'
    //         ], 500);
    //     }
    // }
    // public function deleteProfileImage($userCode)
    // {
    //     try {
    //         $updated = DB::connection('tenant')->update(
    //             "UPDATE USERS
    //              SET PROFILE_IMG = NULL,
    //                  PROFILE_IMG_MIME = NULL
    //              WHERE USER_CODE = ?",
    //             [$userCode]
    //         );

    //         if ($updated === 0) {
    //             $exists = DB::connection('tenant')
    //                 ->table('USERS')
    //                 ->where('USER_CODE', $userCode)
    //                 ->exists();

    //             if (!$exists) {
    //                 return response()->json([
    //                     'message' => 'User not found.',
    //                 ], 404);
    //             }
    //         }

    //         \Log::info('DELETE PROFILE IMAGE SUCCESS', [
    //             'userCode' => $userCode,
    //         ]);

    //         return response()->json([
    //             'message' => 'Profile image deleted successfully.',
    //         ]);
    //     } catch (\Throwable $e) {
    //         \Log::error('DELETE PROFILE IMAGE ERROR', [
    //             'userCode' => $userCode,
    //             'message' => $e->getMessage(),
    //         ]);

    //         return response()->json([
    //             'message' => 'Failed to delete profile image.',
    //         ], 500);
    //     }
    // }

    // User Profile Image
    public function uploadProfileImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'USER_CODE' => 'required|string',
            'PROFILE_IMAGE' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('PROFILE_IMAGE');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid uploaded file.',
                ], 422);
            }

            $binary = file_get_contents($file->getRealPath());
            $hex = strtoupper(bin2hex($binary));
            $mime = $file->getMimeType();

            DB::connection('tenant')->update(
                "UPDATE USERS
             SET PROFILE_IMG = CONVERT(VARBINARY(MAX), ?, 2),
                 PROFILE_IMG_MIME = ?
             WHERE USER_CODE = ?",
                [$hex, $mime, $request->USER_CODE]
            );

            return response()->json([
                'message' => 'Profile image uploaded successfully.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('UPLOAD PROFILE IMAGE ERROR', [
                'userCode' => $request->USER_CODE,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to upload profile image.',
            ], 500);
        }
    }

    public function getProfileImage($userCode)
    {
        try {
            $row = DB::connection('tenant')->selectOne(
                "SELECT PROFILE_IMG, PROFILE_IMG_MIME
             FROM USERS
             WHERE USER_CODE = ?",
                [$userCode]
            );

            if (!$row || !$row->PROFILE_IMG) {
                return response()->json([
                    'message' => 'No image found.',
                ], 404);
            }

            $image = $row->PROFILE_IMG;

            if (is_resource($image)) {
                $image = stream_get_contents($image);
            }

            if (is_string($image) && strncmp($image, '0x', 2) === 0) {
                $decoded = hex2bin(substr($image, 2));
                if ($decoded !== false) {
                    $image = $decoded;
                }
            }

            if (
                is_string($image) &&
                preg_match('/^[0-9A-Fa-f]+$/', $image) &&
                strlen($image) % 2 === 0
            ) {
                $decoded = hex2bin($image);
                if ($decoded !== false) {
                    $image = $decoded;
                }
            }

            if ($image === false || $image === null || $image === '') {
                return response()->json([
                    'message' => 'Invalid image data.',
                ], 500);
            }

            while (ob_get_level()) {
                ob_end_clean();
            }

            return response()->stream(function () use ($image) {
                echo $image;
            }, 200, [
                'Content-Type' => $row->PROFILE_IMG_MIME ?: 'image/jpeg',
                'Content-Length' => strlen($image),
                'Content-Disposition' => 'inline; filename="profile.jpg"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            \Log::error('PROFILE IMAGE ERROR', [
                'userCode' => $userCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch profile image.',
            ], 500);
        }
    }

    public function deleteProfileImage($userCode)
    {
        try {
            $updated = DB::connection('tenant')->update(
                "UPDATE USERS
             SET PROFILE_IMG = NULL,
                 PROFILE_IMG_MIME = NULL
             WHERE USER_CODE = ?",
                [$userCode]
            );

            if ($updated === 0) {
                $exists = DB::connection('tenant')
                    ->table('USERS')
                    ->where('USER_CODE', $userCode)
                    ->exists();

                if (!$exists) {
                    return response()->json([
                        'message' => 'User not found.',
                    ], 404);
                }
            }

            return response()->json([
                'message' => 'Profile image deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('DELETE PROFILE IMAGE ERROR', [
                'userCode' => $userCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete profile image.',
            ], 500);
        }
    }

    public function getSecTrail(Request $request)
    {
        $raw    = $request->input('PARAMS');
        $params = is_array($raw) ? $raw : json_decode($raw, true);

        // Normalize "ALL" sentinel values to null so the sproc's IS NULL checks work
        $tblCode   = ($params['tblCode']    ?? 'ALL') === 'ALL' ? null : ($params['tblCode']    ?? null);
        $activity  = ($params['activity']   ?? 'ALL') === 'ALL' ? null : ($params['activity']   ?? null);
        $refCode   = !empty($params['refCode'])   ? $params['refCode']   : null;
        $doneBy    = !empty($params['doneByCode']) ? $params['doneByCode'] : null;
        $startDate = !empty($params['startDate']) ? $params['startDate'] : null;
        $endDate   = !empty($params['endDate'])   ? $params['endDate']   : null;

        try {
            $results = DB::select(
                'EXEC dbo.sproc_PHP_SecTrail
                @_mode      = ?,
                @_tblCode   = ?,
                @_activity  = ?,
                @_refCode   = ?,
                @_doneBy    = ?,
                @_startDate = ?,
                @_endDate   = ?',
                ['GetAll', $tblCode, $activity, $refCode, $doneBy, $startDate, $endDate]
            );

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}