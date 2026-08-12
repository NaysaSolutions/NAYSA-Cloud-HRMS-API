<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CRController extends Controller
{
   
public function index(Request $request) {

    try {

        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');
      
        $results = DB::select(
            'EXEC sproc_PHP_CR @mode = ?, @params = ?',
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

    $jsonData = $request->all(); 
    $jsonString = json_encode($jsonData); 

    try {
        $results = DB::select(
            'EXEC sproc_PHP_CR @mode = ?, @params = ?',
            ['Get' ,$jsonString] 
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




public function posting(Request $request) {

    try {
        $results = DB::select(
            'EXEC sproc_PHP_CR @mode = ?',
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



public function upsert(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Upsert';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_CR @mode = ?, @params = ?', [
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



public function finalize(Request $request) {

    try {

       $validated = $request->validate([
            'json_data' => 'required|array'
        ]);
        // Already JSON, just assign
        // $params = json_encode($validated['json_data']);
        $params = json_encode(['json_data' => $validated['json_data']]);

          
        $results = DB::select(
            'EXEC sproc_PHP_Posting_CR @mode = ?, @params = ?',
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



public function generateGL(Request $request)
    {
        try {
            $jsonData = $request->input('json_data');

            if (!$jsonData) {
                return response()->json(['error' => 'Missing json_data'], 400);
            }
            $jsonString = json_encode(['json_data' => $jsonData], JSON_UNESCAPED_UNICODE);


            $results = DB::select("EXEC sproc_PHP_CR @mode = ?, @params = ?", [
                'GenerateEntries',
                $jsonString
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing sproc_PHP_CR: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate entries.',
                'details' => $e->getMessage()
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
            $result = DB::select('EXEC sproc_PHP_CR @mode = ?, @params = ?', [
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






public function history(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'History';

            // Call the stored procedure
            $results = DB::select('EXEC sproc_PHP_CR @mode = ?, @params = ?', [
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
                'message' => 'Error executing CR Upsert.',
                'details' => $e->getMessage()
            ], 500);
    }

}



}
