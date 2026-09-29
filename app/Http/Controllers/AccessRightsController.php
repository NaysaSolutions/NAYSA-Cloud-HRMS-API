<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\FixedSystemAccounts;

class AccessRightsController extends Controller
{
    private function fixedAccounts(): FixedSystemAccounts
    {
        return app(FixedSystemAccounts::class);
    }

    private function fixedAccountManagementError(string $userCode)
    {
        return response()->json([
            'success' => false,
            'message' => "{$userCode} is a fixed system account. Its access is controlled by server configuration and cannot be managed here.",
        ], 422);
    }


    /*
    |--------------------------------------------------------------------------
    | Decode SPROC JSON Result
    |--------------------------------------------------------------------------
    */
    private function decodeSprocResult(array $results): array
    {
        $row = $results[0] ?? null;

        if (!$row) {
            return [];
        }

        $arr = (array) $row;

        $raw =
            $arr['result'] ??
            $arr['RESULT'] ??
            null;

        if ($raw === null || $raw === '') {
            return $results;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return is_array($raw)
            ? $raw
            : [];
    }


    /*
    |--------------------------------------------------------------------------
    | Standard SPROC Save Response
    |--------------------------------------------------------------------------
    */
    private function successFromSproc(
        array $results,
        string $successMessage
    ) {
        $row = $results[0] ?? null;
        $arr = $row ? (array) $row : [];

        $errorMsg =
            $arr['errormsg'] ??
            $arr['ERRORMSG'] ??
            '';

        $errorCount = (int) (
            $arr['errorcount'] ??
            $arr['ERRORCOUNT'] ??
            0
        );

        if ($errorCount > 0) {
            return response()->json([
                'success' => false,
                'message' =>
                    $errorMsg ?:
                    'Unable to save access rights.',
                'data' => [
                    'status' => 'error',
                    'errormsg' => $errorMsg,
                    'errorcount' => $errorCount,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'data' => [
                'status' => 'success',
                'errormsg' => '',
                'errorcount' => 0,
            ],
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | Load Users
    |--------------------------------------------------------------------------
    */
    public function load(Request $request)
{
    try {
        $status = $request->input('Status', 'Active');

        $results = DB::connection('tenant')->select(
            'EXEC dbo.sproc_PHP_Users @mode = ?, @params = ?',
            [
                'Load',
                $status,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);

    } catch (\Throwable $e) {
        Log::error('AccessRights load error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}


    /*
    |--------------------------------------------------------------------------
    | GET USER BRANCH ACCESS
    |--------------------------------------------------------------------------
    |
    | POST /getUserBranchAccess
    |
    | {
    |   "json_data": {
    |     "dt2": [
    |       {"userCode":"AGA"},
    |       {"userCode":"CALVIN"}
    |     ]
    |   }
    | }
    |
    */
    public function getUserBranchAccess(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
                'json_data.dt2' => 'required|array|min:1',
                'json_data.dt2.*.userCode' => 'required|string',
            ]);

            $params = json_encode([
                'json_data' => [
                    'dt2' => array_values(
                        $request->input('json_data.dt2', [])
                    ),
                ],
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                [
                    'GetUserBranchAccess',
                    $params,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $this->decodeSprocResult($results),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('getUserBranchAccess error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading User Branch Access.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPSERT USER BRANCH ACCESS
    |--------------------------------------------------------------------------
    */
    public function upsertUserBranchAccess(Request $request)
    {
        try {
            if (
                $fixedCode =
                $this->fixedAccounts()->findInPayload(
                    $request->all()
                )
            ) {
                return $this->fixedAccountManagementError(
                    $fixedCode
                );
            }

            $request->validate([
                'json_data' => 'required|array',
                'json_data.dt1' => 'nullable|array',
                'json_data.dt1.*.branchCode' => 'required|string',
                'json_data.dt2' => 'required|array|min:1',
                'json_data.dt2.*.userCode' => 'required|string',
            ]);

            $params = json_encode([
                'json_data' => [
                    'dt1' => array_values(
                        $request->input('json_data.dt1', [])
                    ),
                    'dt2' => array_values(
                        $request->input('json_data.dt2', [])
                    ),
                ],
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                [
                    'UpsertUserBranchAccess',
                    $params,
                ]
            );

            return $this->successFromSproc(
                $results,
                'User branch access saved successfully.'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('upsertUserBranchAccess error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving User Branch Access.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GET USER MENU ACCESS
    |--------------------------------------------------------------------------
    |
    | POST /getUserMenuAccess
    |
    | {
    |   "json_data": {
    |     "dt2": [
    |       {"userCode":"AGA"},
    |       {"userCode":"CALVIN"}
    |     ]
    |   }
    | }
    |
    */
    public function getUserMenuAccess(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
                'json_data.dt2' => 'required|array|min:1',
                'json_data.dt2.*.userCode' => 'required|string',
            ]);

            $params = json_encode([
                'json_data' => [
                    'dt2' => array_values(
                        $request->input('json_data.dt2', [])
                    ),
                ],
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                [
                    'GetUserMenuAccess',
                    $params,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $this->decodeSprocResult($results),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('getUserMenuAccess error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading User Menu Access.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPSERT USER MENU ACCESS
    |--------------------------------------------------------------------------
    |
    | POST /upsertUserMenuAccess
    |
    | {
    |   "json_data": {
    |     "dt1": [
    |       {
    |         "menuCode":"HR0110",
    |         "permissionType":"FULL"
    |       },
    |       {
    |         "menuCode":"GR0200",
    |         "permissionType":"READ"
    |       }
    |     ],
    |     "dt2": [
    |       {"userCode":"AGA"},
    |       {"userCode":"CALVIN"}
    |     ]
    |   }
    | }
    |
    */
    public function upsertUserMenuAccess(Request $request)
    {
        try {
            if (
                $fixedCode =
                $this->fixedAccounts()->findInPayload(
                    $request->all()
                )
            ) {
                return $this->fixedAccountManagementError(
                    $fixedCode
                );
            }

            $request->validate([
                'json_data' => 'required|array',

                'json_data.dt1' => 'nullable|array',
                'json_data.dt1.*.menuCode' => 'required|string',
                'json_data.dt1.*.permissionType' => 'required|string|in:FULL,READ',

                'json_data.dt2' => 'required|array|min:1',
                'json_data.dt2.*.userCode' => 'required|string',
            ]);

            $dt1 = collect(
                $request->input('json_data.dt1', [])
            )
                ->map(function ($row) {
                    return [
                        'menuCode' => trim(
                            (string) (
                                $row['menuCode'] ?? ''
                            )
                        ),
                        'permissionType' => strtoupper(
                            trim(
                                (string) (
                                    $row['permissionType'] ??
                                    'FULL'
                                )
                            )
                        ) === 'READ'
                            ? 'READ'
                            : 'FULL',
                    ];
                })
                ->filter(
                    fn($row) =>
                        $row['menuCode'] !== ''
                )
                ->unique('menuCode')
                ->values()
                ->all();

            $dt2 = collect(
                $request->input('json_data.dt2', [])
            )
                ->map(function ($row) {
                    return [
                        'userCode' => trim(
                            (string) (
                                $row['userCode'] ?? ''
                            )
                        ),
                    ];
                })
                ->filter(
                    fn($row) =>
                        $row['userCode'] !== ''
                )
                ->unique('userCode')
                ->values()
                ->all();

            if (empty($dt2)) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one User Code is required.',
                ], 422);
            }

            $params = json_encode([
                'json_data' => [
                    'dt1' => $dt1,
                    'dt2' => $dt2,
                ],
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC sproc_PHP_AccessRights @mode = ?, @params = ?',
                [
                    'UpsertUserMenuAccess',
                    $params,
                ]
            );

            return $this->successFromSproc(
                $results,
                'User menu access saved successfully.'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('upsertUserMenuAccess error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving User Menu Access.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
