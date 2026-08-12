<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProdBOMController extends Controller
{
    private function normalizePayload($payload): string
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $normalized = isset($payload['json_data']) ? $payload : ['json_data' => $payload];
        return json_encode($normalized);
    }

    public function index(Request $request)
    {
        try {
            $results = DB::select('EXEC sproc_PHP_ProdBOM @mode = ?', ['Load']);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function get(Request $request)
    {
        $request->validate([
            'BOM_CODE' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_ProdBOM @mode = ?, @params = ?',
                ['Get', $request->BOM_CODE]
            );

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function lookupItem(Request $request)
    {
        $request->validate([
            'PARAMS' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_ProdBOM @mode = ?, @params = ?',
                ['LookupItem', $request->input('PARAMS')]
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
                'EXEC sproc_PHP_ProdBOM @mode = ?, @params = ?',
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
                'message' => 'BOM saved successfully.',
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ProdBOM upsert failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save BOM: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        try {
            $params = $this->normalizePayload($request->input('json_data', $request->all()));
            $results = DB::select(
                'EXEC sproc_PHP_ProdBOM @mode = ?, @params = ?',
                ['CheckDuplicate', $params]
            );

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function checkInUsed(Request $request)
    {
        try {
            $params = $this->normalizePayload($request->input('json_data', $request->all()));
            $results = DB::select(
                'EXEC sproc_PHP_ProdBOM @mode = ?, @params = ?',
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
            'json_data' => 'required|array',
        ]);

        $data = $request->json_data;
        $code = $data['bomCode'] ?? null;

        if (!$code) {
            return response()->json(['success' => false, 'message' => 'BOM Code is required.'], 400);
        }

        try {
            $params = json_encode(['json_data' => $data]);
            $rows = DB::select(
                'EXEC sproc_PHP_ProdBOM @mode = ?, @params = ?',
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
                'message' => 'BOM deleted successfully.',
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
