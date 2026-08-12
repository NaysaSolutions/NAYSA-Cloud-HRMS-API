<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class BankTypeController extends Controller
{

 public function index(Request $request)
    {

        try {
            $results = DB::select(
                'EXEC sproc_PHP_BankRef @mode = ?',
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
                'EXEC sproc_PHP_BankRef @mode = ?, @params = ?',
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
        'bankTypeCode' => 'required|string',
    ]);

    $params = json_encode([
        'json_data' => [
            'bankTypeCode' => $request->input('bankTypeCode')
        ]
    ]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_BankRef @mode = ?, @params = ?',
            ['Get', $params]
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


    public function checkDuplicate(Request $request)
    {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_BankRef @mode = ?, @params = ?',
                ['CheckDuplicate', $params]
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
    try {
        $request->validate([
            'json_data' => 'required', // <-- remove 'json' rule
        ]);

        $params = $request->input('json_data');

        // If React sends object, convert to JSON string WITH wrapper
        if (is_array($params)) {
            $params = json_encode(['json_data' => $params]);
        }

        // If React sends string, ensure it is the wrapped format
        // (optional but safe)
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (!isset($decoded['json_data'])) {
                    $params = json_encode(['json_data' => $decoded]);
                }
            } else {
                throw new \Exception("json_data is not valid JSON.");
            }
        }

        $results = DB::select(
            'EXEC sproc_PHP_BankRef @params = :json_data, @mode = :mode',
            [
                'json_data' => $params,
                'mode' => 'Upsert', // <-- must match sproc
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ], 200);

    } catch (\Exception $e) {
        Log::error('Transaction save failed:', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
    }
}

    public function delete(Request $request)
{
    $request->validate([
        'json_data' => 'required|array',
    ]);

    $data = $request->json_data;   // ← already array
    $code = $data['bankTypeCode'] ?? null;

    if (!$code) {
        return response()->json([
            'success' => false,
            'message' => 'Bank Type Code is required.',
        ], 400);
    }

    try {
        $params = json_encode([
            'json_data' => $data
        ]);

        DB::statement(
            'EXEC sproc_PHP_BankRef @mode = ?, @params = ?',
            ['Delete', $params]
        );

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully.'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

public function checkInUsed(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_BankRef @mode = ?, @params = ?',
            ['CheckInUsed' ,$params] 
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


}
