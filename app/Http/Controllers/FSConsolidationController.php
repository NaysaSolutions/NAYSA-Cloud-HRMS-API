<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class FSConsolidationController extends Controller
{
   
public function index(Request $request) {

    try {
        $results = DB::select(
            'EXEC sproc_PHP_FSConso @mode = ?',
            ['Load' ] 
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
    $params = $request->input('PARAMS', '');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_FSConso @mode = ?, @params = ?',
            ['lookup', $params]
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



public function get(Request $request) {

    $request->validate([
        'ACCT_CODE' => 'required|string',
    ]);

    $params = $request->input('ACCT_CODE');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_FSConso @mode = ?, @params = ?',
            ['Get' ,$params] 
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
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');

        // 1. Use DB::select to capture the Sproc's output (errormsg, errorcount)
        $results = DB::select('EXEC sproc_PHP_FSConso @params = :json_data, @mode = :mode', [
            'json_data' => $params,
            'mode' => 'upsert'
        ]);

        // 2. Return the SQL results so React can read them
        return response()->json([
            'status' => 'success',
            'data' => $results, // <--- This contains your validation table
        ], 200);

    } catch (\Exception $e) {
        Log::error('Saving failed:', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
    }
}



public function upsertGLFSMatching(Request $request)
{
    try {
        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');

        // 1. Use DB::select to capture the Sproc's output (errormsg, errorcount)
        $results = DB::select('EXEC sproc_PHP_FSConso @params = :json_data, @mode = :mode', [
            'json_data' => $params,
            'mode' => 'upsertGLFSMatching'
        ]);

        // 2. Return the SQL results so React can read them
        return response()->json([
            'status' => 'success',
            'data' => $results, // <--- This contains your validation table
        ], 200);

    } catch (\Exception $e) {
        Log::error('Saving failed:', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
    }
}

public function delete(Request $request) {

    try {

      $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);
      

        $results = DB::select(
            'EXEC sproc_PHP_FSConso @mode = ?, @params = ?',
            ['Delete', $params]
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





public function checkInUsed(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_FSConso @mode = ?, @params = ?',
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






public function checkDuplicate(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_FSConso @mode = ?, @params = ?',
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





public function lookupGL(Request $request)
    {
        try {
            // Expecting json_data to be passed from client
            $jsonData = $request->input('json_data');

            if (!$jsonData) {
                return response()->json(['error' => 'Missing json_data'], 400);
            }

            // Convert JSON to string format
            $jsonString = json_encode(['json_data' => $jsonData], JSON_UNESCAPED_UNICODE);

            // Execute the stored procedure
            $results = DB::select("EXEC sproc_PHP_FSConso @mode = ?, @params = ?", [
                'lookupGL',
                $jsonString
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing sproc_PHP_FSConso: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate entries.',
                'details' => $e->getMessage()
            ], 500);
        }
    }




}