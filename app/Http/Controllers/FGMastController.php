<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FGMastController extends Controller
{

    public function index(Request $request)
    {

        try {
            $results = DB::select(
                'EXEC sproc_PHP_FGMast @mode = ?',
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
                'EXEC sproc_PHP_FGMast @mode = ?, @params = ?',
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
            'ITEM_CODE' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_FGMast @mode = ?, @params = ?',
                ['Get', $request->ITEM_CODE]
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
        // 1. Accept either string or array
        $request->validate([
            'json_data' => 'required',
        ]);

        try {
            $payload = $request->input('json_data');

            // 2. If Axios sent it as a string, decode it so we can inspect it
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
            }

            // 3. Guarantee the structure matches what the SQL SPROC expects: $.json_data.itemCode
            $normalized = isset($payload['json_data']) ? $payload : ['json_data' => $payload];

            // 4. Re-encode to a perfectly clean JSON string for SQL Server
            $params = json_encode($normalized);

            $rows = DB::select(
                'EXEC sproc_PHP_FGMast @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            $r0 = $rows[0] ?? null;
            $errorcount = (int)($r0->errorcount ?? 0);
            $errormsg = (string)($r0->errormsg ?? '');

            // ? If sproc returned validation error
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
                'message' => 'Transaction saved successfully.',
                'data' => $rows,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Upsert failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    
    public function checkInUsed(Request $request) 
    {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_FGMast @mode = ?, @params = ?',
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






    public function checkDuplicate(Request $request) 
    {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_FGMast @mode = ?, @params = ?',
                ['CheckDuplicate' ,$params] 
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

    public function delete(Request $request)
    {
        $request->validate([
            'json_data' => 'required|array',
        ]);

        $data = $request->json_data;   // ? already array
        $code = $data['itemCode'] ?? null; // using 'itemCode' to match React frontend state

        if (!$code) {
            return response()->json([
                'success' => false,
                'message' => 'Item code is required.',
            ], 400);
        }

        try {
            $params = json_encode([
                'json_data' => $data
            ]);

            DB::statement(
                'EXEC sproc_PHP_FGMast @mode = ?, @params = ?',
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
}