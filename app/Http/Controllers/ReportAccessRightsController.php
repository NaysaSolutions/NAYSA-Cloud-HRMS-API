<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportAccessRightsController extends Controller
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

        $errorMsg   = $arr['errormsg']   ?? $arr['ERRORMSG']   ?? '';
        $errorCount = (int) ($arr['errorcount'] ?? $arr['ERRORCOUNT'] ?? 0);

        if ($errorCount > 0) {
            return response()->json([
                'success' => false,
                'message' => $errorMsg ?: 'Unable to save report access rights.',
                'data'    => [
                    'status'     => 'error',
                    'errormsg'   => $errorMsg,
                    'errorcount' => $errorCount,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'data'    => [
                'status'     => 'success',
                'errormsg'   => '',
                'errorcount' => 0,
            ],
        ], 200);
    }

    public function loadReportData(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_ReportAccessRights @mode = ?',
                ['LoadReportData']
            );

            return response()->json([
                'success' => true,
                'data'    => $this->decodeSprocResult($results),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('loadReportData error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load report data.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserReportData(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|array',
            ]);

            $params = $this->encodeParams($request->input('json_data'));

            $results = DB::select(
                'EXEC sproc_PHP_ReportAccessRights @mode = ?, @params = ?',
                ['GetUserReportData', $params]
            );

            return response()->json([
                'success' => true,
                'data'    => $this->decodeSprocResult($results),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('getUserReportData error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get user report access.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsertUserReportData(Request $request)
    {
        try {
            $request->validate([
                'json_data'      => 'required|array',
                'json_data.dt1'  => 'required|array',
                'json_data.dt2'  => 'required|array',
            ]);

            $params = $this->encodeParams($request->input('json_data'));

            $results = DB::select(
                'EXEC sproc_PHP_ReportAccessRights @mode = ?, @params = ?',
                ['UpsertUserReportData', $params]
            );

            return $this->successFromSproc($results, 'User report access saved successfully.');
        } catch (\Throwable $e) {
            Log::error('upsertUserReportData error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving user report access.',
                'details' => $e->getMessage(),
                'data'    => ['status' => 'error'],
            ], 500);
        }
    }

    public function deleteUserReportData(Request $request)
    {
        try {
            $request->validate([
                'json_data'      => 'required|array',
                'json_data.dt1'  => 'required|array',
                'json_data.dt2'  => 'required|array',
            ]);

            $params = $this->encodeParams($request->input('json_data'));

            $results = DB::select(
                'EXEC sproc_PHP_ReportAccessRights @mode = ?, @params = ?',
                ['DeleteUserReportData', $params]
            );

            return $this->successFromSproc($results, 'User report access deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('deleteUserReportData error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting user report access.',
                'details' => $e->getMessage(),
                'data'    => ['status' => 'error'],
            ], 500);
        }
    }
}