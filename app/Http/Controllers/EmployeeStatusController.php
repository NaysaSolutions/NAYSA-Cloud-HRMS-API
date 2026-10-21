<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeStatusController extends Controller
{
    public function index(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefEmployeeStatus @mode = ?',
                ['Load']
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function lookup(Request $request)
    {
        $request->validate([
            'PARAMS' => 'required|string',
        ]);

        $params = $request->input('PARAMS');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefEmployeeStatus @mode = ?, @params = ?',
                ['Lookup', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function get(Request $request)
    {
        $request->validate([
            'STAT_CODE' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefEmployeeStatus @mode = ?, @params = ?',
                ['Get', $request->STAT_CODE]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsert(Request $request)
    {
        $request->validate([
            'json_data' => 'required',
        ]);

        $data = $request->input('json_data');

        try {
            if (is_string($data)) {
                $decoded = json_decode($data, true);

                if (
                    json_last_error() !== JSON_ERROR_NONE ||
                    !is_array($decoded)
                ) {
                    return response()->json([
                        'errormsg' => 'Invalid json_data format.',
                        'errorcount' => 1,
                    ], 422);
                }

                if (array_key_exists('json_data', $decoded)) {
                    $params = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                } else {
                    $params = json_encode(
                        ['json_data' => $decoded],
                        JSON_UNESCAPED_UNICODE
                    );
                }
            } else {
                $params = json_encode(
                    ['json_data' => $data],
                    JSON_UNESCAPED_UNICODE
                );
            }

            $result = DB::select(
                'EXEC sproc_PHP_RefEmployeeStatus @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            return response()->json([
                'errormsg' => $result[0]->errormsg ?? '',
                'errorcount' => $result[0]->errorcount ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Employee Status upsert failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'errormsg' => $e->getMessage(),
                'errorcount' => 1,
            ], 500);
        }
    }

    public function checkInUsed(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array',
            'json_data.statCode' => 'required|string',
        ]);

        $params = json_encode($validated, JSON_UNESCAPED_UNICODE);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefEmployeeStatus @mode = ?, @params = ?',
                ['CheckInUsed', $params]
            );

            $raw = $results[0]->result ?? '{"result":"0"}';
            $decoded = json_decode($raw, true);

            return response()->json([
                'success' => true,
                'isInUsed' => ($decoded['result'] ?? '0') === '1',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array',
            'json_data.statCode' => 'required|string',
        ]);

        $params = json_encode($validated, JSON_UNESCAPED_UNICODE);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefEmployeeStatus @mode = ?, @params = ?',
                ['CheckDuplicate', $params]
            );

            $raw = $results[0]->result ?? '{"result":"0"}';
            $decoded = json_decode($raw, true);

            return response()->json([
                'success' => true,
                'result' => $decoded['result'] ?? '0',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $request->validate([
            'json_data' => 'required|array',
            'json_data.statCode' => 'required|string',
        ]);

        $data = $request->input('json_data');
        $statCode = $data['statCode'] ?? null;

        if (!$statCode) {
            return response()->json([
                'success' => false,
                'message' => 'Status Code is required.',
            ], 400);
        }

        try {
            $params = json_encode(
                ['json_data' => $data],
                JSON_UNESCAPED_UNICODE
            );

            $result = DB::select(
                'EXEC sproc_PHP_RefEmployeeStatus @mode = ?, @params = ?',
                ['Delete', $params]
            );

            $errorCount = $result[0]->errorcount ?? 0;
            $errorMsg = $result[0]->errormsg ?? '';

            if ($errorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg ?: 'Unable to delete Employee Status.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Employee Status delete failed', [
                'statCode' => $statCode,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
