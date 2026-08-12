<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class JournalVoucherController extends Controller
{
    
public function index(Request $request) {

    try {

        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');
      
        $results = DB::select(
            'EXEC sproc_PHP_JV @mode = ?, @params = ?',
            ['get' ,$params] 
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



    $jsonData = $request->all(); // This will be e.g., ['sviNo' => 'SVI-001']
    $jsonString = json_encode($jsonData); // This will be e.g., '{"sviNo":"SVI-001"}'

    try {
        $results = DB::select(
            'EXEC sproc_PHP_JV @mode = ?, @params = ?',
            ['Get' ,$jsonString] // Mode is 'Get', params is '{"sviNo":"SVI-001"}'
        );

        return response()->json([
            'success' => true,
            'data' => $results, // $results will contain the 'result' column from SPROC
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
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Upsert';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_JV @mode = ?, @params = ?', [
                $mode,
                $params
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing JV Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}




public function posting(Request $request) {

    try {
        $results = DB::select(
            'EXEC sproc_PHP_JV @mode = ?',
            ['Posting'] 
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



public function finalize(Request $request) {

    try {

       $validated = $request->validate([
            'json_data'     => 'required|array'
        ]);
        // Already JSON, just assign
        // $params = json_encode($validated['json_data']);
        $params = json_encode(['json_data' => $validated['json_data']]);

          
        $results = DB::select(
            'EXEC sproc_PHP_Posting_JV @mode = ?, @params = ?',
            ['Finalize', $params]
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



public function cancel(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Cancel';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_JV @mode = ?, @params = ?', [
                $mode,
                $params
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing SVI Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}


public function post(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Post';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_JV @mode = ?, @params = ?', [
                $mode,
                $params
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing JV Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}


public function reversal(Request $request) {



    $jsonData = $request->all(); // This will be e.g., ['sviNo' => 'SVI-001']
    $jsonString = json_encode($jsonData); // This will be e.g., '{"sviNo":"SVI-001"}'

    try {
        $results = DB::select(
            'EXEC sproc_PHP_JV @mode = ?, @params = ?',
            ['reversal' ,$jsonString] // Mode is 'Get', params is '{"sviNo":"SVI-001"}'
        );

        return response()->json([
            'success' => true,
            'data' => $results, // $results will contain the 'result' column from SPROC
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }

}








public function history(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'History';

            // Call the stored procedure
            $results = DB::select('EXEC sproc_PHP_JV @mode = ?, @params = ?', [
                $mode,
                $params
            ]);
       
         return response()->json([
                'status' => 'success',
                'data' => $results
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing PCV Upsert.',
                'details' => $e->getMessage()
            ], 500);
    }

}





}
