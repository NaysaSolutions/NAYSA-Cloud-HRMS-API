<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MasterAccessRightsController extends Controller
{
    private function encodeParams(array $jsonData): string
    {
        return json_encode(['json_data' => $jsonData], JSON_UNESCAPED_UNICODE);
    }

    private function decodeSprocResult(array $results): array
    {
        $row = $results[0] ?? null;

        if (!$row) {
            return [];
        }

        $arr = (array) $row;
        $raw = $arr['result'] ?? $arr['RESULT'] ?? null;

        if ($raw === null || $raw === '') {
            return $results;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    private function successFromSproc(array $results, string $successMessage)
    {
        $row = $results[0] ?? null;
        $arr = $row ? (array) $row : [];

        $errorMsg = $arr['errormsg'] ?? $arr['ERRORMSG'] ?? '';
        $errorCount = (int) ($arr['errorcount'] ?? $arr['ERRORCOUNT'] ?? 0);

        if ($errorCount > 0) {
            return response()->json([
                'success' => false,
                'message' => $errorMsg ?: 'Unable to save master access rights.',
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

    public function loadMasterData(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_MasterAccessRights @mode = ?',
                ['LoadMasterData']
            );

            return response()->json([
                'success' => true,
                'data' => $this->decodeSprocResult($results),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('loadMasterData error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load master data.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserMasterData(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
            ]);

            $params = $this->encodeParams($request->input('json_data'));

            $results = DB::select(
                'EXEC sproc_PHP_MasterAccessRights @mode = ?, @params = ?',
                ['GetUserMasterData', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $this->decodeSprocResult($results),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('getUserMasterData error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get user master data access.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsertUserMasterData(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
                'json_data.dt1' => 'required|array',
                'json_data.dt2' => 'required|array',
            ]);

            $params = $this->encodeParams($request->input('json_data'));

            $results = DB::select(
                'EXEC sproc_PHP_MasterAccessRights @mode = ?, @params = ?',
                ['UpsertUserMasterData', $params]
            );

            return $this->successFromSproc($results, 'User master data access saved successfully.');
        } catch (\Throwable $e) {
            Log::error('upsertUserMasterData error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving user master data access.',
                'details' => $e->getMessage(),
                'data' => ['status' => 'error'],
            ], 500);
        }
    }

    public function deleteUserMasterData(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
                'json_data.dt1' => 'required|array',
                'json_data.dt2' => 'required|array',
            ]);

            $params = $this->encodeParams($request->input('json_data'));

            $results = DB::select(
                'EXEC sproc_PHP_MasterAccessRights @mode = ?, @params = ?',
                ['DeleteUserMasterData', $params]
            );

            return $this->successFromSproc($results, 'User master data access deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('deleteUserMasterData error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting user master data access.',
                'details' => $e->getMessage(),
                'data' => ['status' => 'error'],
            ], 500);
        }
    }
}
