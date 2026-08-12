<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccessRightsController extends Controller
{
    public function upsertRole(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
            ]);

            $jsonData = $request->input('json_data');
            $params   = json_encode([
                'json_data' => $jsonData
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @params = ?, @mode = ?',
                [$params, 'UpsertRole']
            );

            $row = $results[0] ?? null;
            $arr = $row ? (array) $row : [];

            $errorMsg   = $arr['errormsg'] ?? $arr['ERRORMSG'] ?? '';
            $errorCount = (int)($arr['errorcount'] ?? $arr['ERRORCOUNT'] ?? 0);

            if ($errorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg ?: 'Unable to save role.',
                    'data'    => [
                        'status'     => 'error',
                        'errormsg'   => $errorMsg,
                        'errorcount' => $errorCount,
                    ],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role saved successfully.',
                'data'    => [
                    'status'     => 'success',
                    'errormsg'   => '',
                    'errorcount' => 0,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('upsertRole error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error executing Role Upsert.',
                'details' => $e->getMessage(),
                'data'    => [
                    'status' => 'error',
                ],
            ], 500);
        }
    }

    public function loadRole(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?',
                ['LoadRole']
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('loadRole error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRole(Request $request)
    {
        $request->validate([
            'ROLE_CODE' => 'required|string',
        ]);

        $params = $request->input('ROLE_CODE');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                ['GetRole', $params]
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('getRole error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteRole(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
                'json_data.roleCode' => 'required|string',
                'json_data.userCode' => 'nullable|string',
                'json_data.roleName' => 'nullable|string',
            ]);

            $jsonData = $request->input('json_data');
            $params   = json_encode([
                'json_data' => $jsonData
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                ['DeleteRole', $params]
            );

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully.',
                'data'    => [
                    'status'  => 'success',
                    'results' => $results,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('deleteRole error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        $request->validate([
            'ROLE_CODE' => 'required|string',
        ]);

        $params = $request->input('ROLE_CODE');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                ['CheckDuplicate', $params]
            );

            $raw = $results[0]->result ?? ($results[0]->RESULT ?? '{"result":"0"}');
            $decoded = json_decode($raw, true);

            return response()->json([
                'success' => true,
                'data'    => [
                    'result'      => $decoded['result'] ?? '0',
                    'isDuplicate' => ($decoded['result'] ?? '0') === '1',
                    'raw'         => $results,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('checkDuplicate error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkInUsed(Request $request)
    {
        $request->validate([
            'ROLE_CODE' => 'required|string',
        ]);

        $params = $request->input('ROLE_CODE');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                ['CheckInUsed', $params]
            );

            $raw = $results[0]->result ?? ($results[0]->RESULT ?? '{"result":"0"}');
            $decoded = json_decode($raw, true);

            return response()->json([
                'success' => true,
                'data'    => [
                    'result' => $decoded['result'] ?? '0',
                    'isUsed' => ($decoded['result'] ?? '0') === '1',
                    'raw'    => $results,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('checkInUsed error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRoleMenu(Request $request)
    {
        try {
            $request->validate([
                'ROLE_CODE' => 'required|string',
            ]);

            $params = $request->input('ROLE_CODE');

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                ['GetRoleMenu', $params]
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('getRoleMenu error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsertRoleMenu(Request $request)
    {
        try {
            $request->validate([
                'json_data.roleCode' => 'required_without:ROLE_CODE|string',
                'ROLE_CODE'          => 'required_without:json_data.roleCode|string',
                'json_data.dt1'      => 'array',
            ]);

            $roleCode = $request->input('json_data.roleCode') ?? $request->input('ROLE_CODE');
            $dt1      = $request->input('json_data.dt1', []);

            $params = json_encode([
                'json_data' => [
                    'roleCode' => $roleCode,
                    'dt1'      => $dt1,
                ]
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @params = ?, @mode = ?',
                [$params, 'UpsertRoleMenu']
            );

            $row = $results[0] ?? null;
            $arr = $row ? (array) $row : [];

            $errorMsg   = $arr['errormsg'] ?? $arr['ERRORMSG'] ?? '';
            $errorCount = (int)($arr['errorcount'] ?? $arr['ERRORCOUNT'] ?? 0);

            if ($errorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg ?: 'Unable to save role menu.',
                    'data'    => [
                        'status'     => 'error',
                        'errormsg'   => $errorMsg,
                        'errorcount' => $errorCount,
                    ],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role menu saved successfully.',
                'data'    => [
                    'status'     => 'success',
                    'errormsg'   => '',
                    'errorcount' => 0,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('upsertRoleMenu error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error executing Role Menu Upsert.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsertUserRole(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
            ]);

            $jsonData = $request->input('json_data');
            $params   = json_encode([
                'json_data' => $jsonData
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @params = ?, @mode = ?',
                [$params, 'UpsertUserRole']
            );

            $row = $results[0] ?? null;
            $arr = $row ? (array) $row : [];

            $errorMsg   = $arr['errormsg'] ?? $arr['ERRORMSG'] ?? '';
            $errorCount = (int)($arr['errorcount'] ?? $arr['ERRORCOUNT'] ?? 0);

            if ($errorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg ?: 'Unable to save user role.',
                    'data'    => [
                        'status'     => 'error',
                        'errormsg'   => $errorMsg,
                        'errorcount' => $errorCount,
                    ],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'User Role saved successfully.',
                'data'    => [
                    'status'     => 'success',
                    'errormsg'   => '',
                    'errorcount' => 0,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('upsertUserRole error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error executing User Role.',
                'details' => $e->getMessage(),
                'data'    => [
                    'status' => 'error',
                ],
            ], 500);
        }
    }

    public function getUserRoles(Request $request)
    {
        try {
            $userCodesParam = $request->query('userCodes');
            $userCodes = $userCodesParam
                ? array_values(array_filter(array_map('trim', explode(',', $userCodesParam))))
                : [];

            if (!empty($userCodes)) {
                $params = json_encode([
                    'json_data' => [
                        'dt1' => array_map(fn($code) => ['userCode' => $code], $userCodes)
                    ]
                ], JSON_UNESCAPED_UNICODE);

                $results = DB::select(
                    'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                    ['GetUserRoles', $params]
                );
            } else {
                $rows = DB::table('USERROLE_REF')
                    ->selectRaw('user_code as userCode, role_code as roleCode')
                    ->get();

                return response()->json([
                    'success' => true,
                    'data'    => $rows,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('getUserRoles error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteUserRole(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
                'json_data.dt1' => 'required|array',
                'json_data.dt2' => 'required|array',
            ]);

            $payload = $request->input('json_data');
            $dt1 = $payload['dt1'] ?? [];
            $dt2 = $payload['dt2'] ?? [];

            foreach ($dt1 as $r) {
                foreach ($dt2 as $u) {
                    DB::table('USERROLE_REF')
                        ->where('user_code', $u['userCode'] ?? '')
                        ->where('role_code', $r['roleCode'] ?? '')
                        ->delete();
                }
            }

            return response()->json([
                'success' => true,
                'data'    => ['status' => 'success'],
                'message' => 'User role deleted successfully.',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('deleteUserRole error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function load(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_Users @mode = ?',
                ['getUsers']
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('load users error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsert(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
            ]);

            $jsonData = $request->input('json_data');
            $params   = json_encode($jsonData);

            DB::statement(
                'EXEC sproc_PHP_Users @params = ?, @mode = ?',
                [$params, 'upsert']
            );

            return response()->json([
                'success' => true,
                'data'    => ['status' => 'success'],
                'message' => 'User saved successfully.',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('upsert user error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'data'    => ['status' => 'error'],
                'message' => 'Error executing User Upsert.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}