<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkCenterController extends Controller
{
    private function decodePayload($payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        if (is_array($payload) && array_key_exists('json_data', $payload)) {
            $inner = $payload['json_data'];

            if (is_string($inner)) {
                $decodedInner = json_decode($inner, true);
                $inner = is_array($decodedInner) ? $decodedInner : [];
            }

            return is_array($inner) ? $inner : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function normalizePayload($payload): string
    {
        return json_encode(['json_data' => $this->decodePayload($payload)]);
    }

    private function parseResultFlag(array $rows): string
    {
        $row = $rows[0] ?? null;
        if (!$row) {
            return '0';
        }

        $rowArray = (array) $row;
        $rawValue = $rowArray['result'] ?? reset($rowArray);

        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            return (string) ($decoded['result'] ?? '0');
        }

        if (is_array($rawValue)) {
            return (string) ($rawValue['result'] ?? '0');
        }

        return '0';
    }

    public function index(Request $request)
    {
        try {
            $results = DB::select('EXEC sproc_PHP_WorkOrderRef @mode = ?', ['Load']);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function get(Request $request)
    {
        // The stored procedure expects wcCode to be passed.
        $request->validate([
            'wcCode' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_WorkOrderRef @mode = ?, @params = ?',
                ['Get', $request->wcCode]
            );

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function lookup(Request $request)
    {
        // Accepts filter parameters for the modal/dropdown
        try {
            $filter = $request->query('PARAMS', '');
            
            $results = DB::select(
                'EXEC sproc_PHP_WorkOrderRef @mode = ?, @params = ?',
                ['Lookup', $filter]
            );

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $request->validate([
            'json_data' => 'required',
        ]);

        try {
            $params = $this->normalizePayload($request->input('json_data'));
            $rows = DB::select(
                'EXEC sproc_PHP_WorkOrderRef @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            $r0 = $rows[0] ?? null;
            $errorcount = (int)($r0->errorcount ?? 0);
            $errormsg = (string)($r0->errormsg ?? '');

            if ($errorcount > 0) {
                return response()->json([
                    'success' => false,
                    'errorcount' => $errorcount,
                    'errormsg' => $errormsg,
                    'data' => $rows,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Work Center saved successfully.',
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Work Center upsert failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save Work Center: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        $request->validate([
            'json_data' => 'required',
        ]);

        try {
            $params = $this->normalizePayload($request->input('json_data'));
            $results = DB::select(
                'EXEC sproc_PHP_WorkOrderRef @mode = ?, @params = ?',
                ['CheckDuplicate', $params]
            );

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function checkInUsed(Request $request)
    {
        $request->validate([
            'json_data' => 'required',
        ]);

        try {
            $params = $this->normalizePayload($request->input('json_data'));
            $results = DB::select(
                'EXEC sproc_PHP_WorkOrderRef @mode = ?, @params = ?',
                ['CheckInUsed', $params]
            );

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        $request->validate([
            'json_data' => 'required',
        ]);

        $data = $this->decodePayload($request->input('json_data'));
        $code = $data['wcCode'] ?? null;

        if (!$code) {
            return response()->json(['success' => false, 'message' => 'Work Center Code is required.'], 400);
        }

        try {
            $params = $this->normalizePayload($request->input('json_data'));

            $usedRows = DB::select(
                'EXEC sproc_PHP_WorkOrderRef @mode = ?, @params = ?',
                ['CheckInUsed', $params]
            );

            if ($this->parseResultFlag($usedRows) === '1') {
                return response()->json([
                    'success' => false,
                    'errorcount' => 1,
                    'errormsg' => "WC Code {$code} is currently in use and cannot be deleted.",
                    'data' => [[
                        'errorcount' => 1,
                        'errormsg' => "WC Code {$code} is currently in use and cannot be deleted.",
                    ]],
                ], 200);
            }

            $rows = DB::select(
                'EXEC sproc_PHP_WorkOrderRef @mode = ?, @params = ?',
                ['Delete', $params]
            );

            $r0 = $rows[0] ?? null;
            $errorcount = (int)($r0->errorcount ?? 0);
            $errormsg = (string)($r0->errormsg ?? '');

            if ($errorcount > 0) {
                return response()->json([
                    'success' => false,
                    'errorcount' => $errorcount,
                    'errormsg' => $errormsg,
                    'data' => $rows,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Work Center deleted successfully.',
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}