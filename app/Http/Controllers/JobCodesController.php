<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class JobCodesController extends Controller
{

    public function index(Request $request)
    {
        try {
            $results = DB::select('EXEC sproc_PHP_JobCodeRef @mode = ?', ['Load']);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add or Update RC Type (POST - already JSON)
     */
    public function upsert(Request $request)
    {
        try {
            // React already sends json_data as a stringified JSON
            $params = $request->input('json_data');

            $results = DB::select('EXEC sproc_PHP_JobCodeRef @mode = :mode, @params = :json_data', [
                'mode' => 'Upsert',
                'json_data' => $params,
            ]);

            return response()->json(['status' => 'success', 'data' => $results], 200);
        } catch (\Exception $e) {
            Log::error('Upsert failed:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to save: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete RC Type (POST - already JSON)
     */
public function delete(Request $request) {

    try {

      $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);
      

        $results = DB::select(
            'EXEC sproc_PHP_JobCodeRef @mode = ?, @params = ?',
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

    /**
     * Get a single RC Type (GET - Convert to JSON)
     */
public function get(Request $request) {

    $request->validate([
        'JOB_CODE' => 'required|string',
    ]);

    $params = $request->input('JOB_CODE');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_JobCodeRef @mode = ?, @params = ?',
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


    
    public function checkInUsed(Request $request) {

            $validated = $request->validate([
                'json_data' => 'required|array'
            ]);

            $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_JobCodeRef @mode = ?, @params = ?',
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
                'EXEC sproc_PHP_JobCodeRef @mode = ?, @params = ?',
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

    /**
     * Lookup for Dropdowns (GET - Convert to JSON)
     */
        
    public function lookup(Request $request) {

        $request->validate([
            'PARAMS' => 'required|string',
        ]);

        $params = $request->input('PARAMS');


        try {
            $results = DB::select(
                'EXEC sproc_PHP_JobCodeRef @mode = ?, @params = ?',
                ['Lookup' ,$params] 
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
