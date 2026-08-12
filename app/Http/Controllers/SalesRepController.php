<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class SalesRepController extends Controller
{

 public function index(Request $request)
    {

        try {
            $results = DB::select(
                'EXEC sproc_PHP_SalesRep @mode = ?',
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
                'EXEC sproc_PHP_SalesRep @mode = ?, @params = ?',
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
        'salesRepCode' => 'required|string',
    ]);

    $params = json_encode([
        'json_data' => [
            'salesRepCode' => $request->input('salesRepCode')
        ]
    ]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_SalesRep @mode = ?, @params = ?',
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
                'EXEC sproc_PHP_SalesRep @mode = ?, @params = ?',
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
        $params = $request->input('json_data');

        // Handle array conversion
        if (is_array($params)) {
            $params = json_encode(['json_data' => $params]);
        }

        // Execute the procedure
        $results = DB::select(
            'EXEC sproc_PHP_SalesRep @mode = :mode, @params = :json_data',
            [
                'mode' => 'Upsert',
                'json_data' => $params,
            ]
        );

        // Check if the SP returned our custom validation error
        // $results will contain the row selected in the SPROC
        if (!empty($results) && isset($results[0]->errorcount) && $results[0]->errorcount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => $results[0]->errormsg,
            ], 422); // 422 Unprocessable Entity is best for validation errors
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Saved successfully.',
        ], 200);

    } catch (\Exception $e) {
        Log::error('Transaction save failed:', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'error',
            'message' => 'An unexpected error occurred.',
        ], 500);
    }
}

    public function delete(Request $request)
{
    $request->validate([
        'json_data' => 'required|array',
    ]);

    $data = $request->json_data;   // ← already array
    $code = $data['salesRepCode'] ?? null;

    if (!$code) {
        return response()->json([
            'success' => false,
            'message' => 'Sales Rep Code is required.',
        ], 400);
    }

    try {
        $params = json_encode([
            'json_data' => $data
        ]);

        DB::statement(
            'EXEC sproc_PHP_SalesRep @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_SalesRep @mode = ?, @params = ?',
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
