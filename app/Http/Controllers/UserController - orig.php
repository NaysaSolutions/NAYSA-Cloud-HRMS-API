<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\TempPasswordMail;
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
                            'userCode'     => $userCode,
                            'userName'     => $user['userName'] ?? '',
                            'userType'     => $user['userType'] ?? 'R',
                            'branchCode'   => $user['branchCode'] ?? '',
                            'rcCode'       => $user['rcCode'] ?? '',
                            'viewCostamt'  => $user['viewCostamt'] ?? 'N',
                            'editUprice'   => $user['editUprice'] ?? 'N',
                            'emailAdd'     => $user['emailAdd'],
                            'position'     => $user['position'] ?? '',
                            'active'       => 'Y',   // ✅ THIS FIXES YOUR ISSUE
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
        $mode = $req->input('mode'); // admin_add | release | reset | null

        $rules = [
            'userCode'    => 'required|string',
            'newPassword' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/\d/',
                'regex:/[^A-Za-z0-9]/',
                'not_regex:/\s/',
            ],
        ];

        if (!in_array($mode, ['reset', 'release'], true)) {
            $rules['oldPassword'] = 'required|string';
        }

        $validated = $req->validate($rules);
        $userCode  = trim($validated['userCode']);

        $row = DB::table('users')->select('password')->where('user_code', $userCode)->first();
        if (!$row) {
            return response()->json(['status' => 'error', 'message' => 'User not found.'], 422);
        }

        if (!in_array($mode, ['reset', 'release'], true)) {
            if (!Hash::check($req->input('oldPassword'), $row->password)) {
                return response()->json(['status' => 'error', 'message' => 'Old password is incorrect.'], 422);
            }
        }

        if (Hash::check($validated['newPassword'], $row->password)) {
            return response()->json(['status' => 'error', 'message' => 'New password must differ from old password.'], 422);
        }

        $hash = Hash::make($validated['newPassword']);

        DB::select("exec sproc_PHP_Users ?, ?", [
            'SetChangedPassword',
            json_encode(['json_data' => [
                'userCode'     => $userCode,
                'passwordHash' => $hash,
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

}
