<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProdParametersController extends Controller
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

    private function parseJsonResult(array $rows): array
    {
        $row = $rows[0] ?? null;

        if (!$row) {
            return [];
        }

        $rowArray = (array) $row;
        $rawValue = $rowArray['result'] ?? reset($rowArray);

        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($rawValue) ? $rawValue : [];
    }

    public function index(Request $request)
    {
        try {
            $rows = DB::select('EXEC sproc_PHP_Prod_Parm @mode = ?', ['Load']);

            return response()->json([
                'success' => true,
                'data' => $this->parseJsonResult($rows),
                'raw' => $rows,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Production Parameters load failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load Production Parameters: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function get(Request $request)
    {
        try {
            $rows = DB::select('EXEC sproc_PHP_Prod_Parm @mode = ?', ['Get']);

            return response()->json([
                'success' => true,
                'data' => $this->parseJsonResult($rows),
                'raw' => $rows,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Production Parameters get failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get Production Parameters: ' . $e->getMessage(),
            ], 500);
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
                'EXEC sproc_PHP_Prod_Parm @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            $r0 = $rows[0] ?? null;
            $errorcount = (int) ($r0->errorcount ?? 0);
            $errormsg = (string) ($r0->errormsg ?? '');

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
                'message' => 'Production Parameters saved successfully.',
                'data' => $rows,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Production Parameters upsert failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save Production Parameters: ' . $e->getMessage(),
            ], 500);
        }
    }
}
