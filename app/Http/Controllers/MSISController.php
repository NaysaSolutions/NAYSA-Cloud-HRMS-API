<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MSISController extends Controller
{
     
public function index(Request $request) {

    try {

        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');
      
        $results = DB::select(
            'EXEC sproc_PHP_MSIS @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_MSIS @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_MSIS @mode = ?',
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
            $result = DB::select('EXEC sproc_PHP_MSIS @mode = ?, @params = ?', [
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
                'message' => 'Error executing MSIS Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}




public function finalize(Request $request) {

    try {

       $validated = $request->validate([
            'json_data'     => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

          
        $results = DB::select(
            'EXEC sproc_PHP_Posting_MSIS @mode = ?, @params = ?',
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


            $results = DB::select("EXEC sproc_PHP_MSIS @mode = ?, @params = ?", [
                'GenerateEntries',
                $jsonString
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing sproc_PHP_MSIS: ' . $e->getMessage());
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
            $result = DB::select('EXEC sproc_PHP_MSIS @mode = ?, @params = ?', [
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
                'message' => 'Error executing MSIS Upsert.',
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
            $results = DB::select('EXEC sproc_PHP_MSIS @mode = ?, @params = ?', [
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
                'message' => 'Error executing MSIS Upsert.',
                'details' => $e->getMessage()
            ], 500);
    }

}

 public function msLookup(Request $request)
    {
        $validated = $request->validate([
            'whouseCode' => 'nullable|string|max:100',
            'locCode'    => 'nullable|string|max:100',
            'docType'    => 'required|string|max:20', // expect "MSIS"
            'tranType'   => 'nullable', // can
            'userCode'   => 'nullable|string|max:50',
        ]);

        $mode = 'Lookup';

        $payload = [
            'json_data' => [
                'dt1'        => [],
                'userCode'   => $validated['userCode'] ?? '',
                'whouseCode' => $validated['whouseCode'] ?? '',
                'locCode'    => $validated['locCode'] ?? '',
                'docType'    => $validated['docType'],     
                'tranType'    => $validated['tranType'],         // must be MSIS to run
            ],
        ];


        $mode = $validated['mode'] ?? 'Lookup';
        $params = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // ✅ Call the stored procedure
        // Your SP returns a single row with a column named "result" (JSON text)
        $rows = DB::select(
            "EXEC dbo.sproc_PHP_INVLookup_MS @mode = ?, @params = ?",
            [$mode, $params]
        );

        $resultJson = $rows[0]->result ?? '[]';

        // If result is a JSON string, decode and return as real JSON array (recommended)
        $decoded = json_decode($resultJson, true);

        // If decoding fails, still return raw to avoid breaking clients
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'ok' => true,
                'result_raw' => $resultJson,
                'warning' => 'Result is not valid JSON',
            ]);
        }

        return response()->json([
            'ok' => true,
            'result' => $decoded,
        ]);
    }

}








