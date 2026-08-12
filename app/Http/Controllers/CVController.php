<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CVController extends Controller
{
   
public function index(Request $request) {

    try {

        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');
      
        $results = DB::select(
            'EXEC sproc_PHP_CV @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_CV @mode = ?, @params = ?',
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





public function upsert(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Upsert';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_CV @mode = ?, @params = ?', [
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
                'message' => 'Error executing CV Upsert.',
                'details' => $e->getMessage()
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


            $results = DB::select("EXEC sproc_PHP_CV @mode = ?, @params = ?", [
                'GenerateEntries',
                $jsonString
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing sproc_PHP_CV: ' . $e->getMessage());
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
            $result = DB::select('EXEC sproc_PHP_CV @mode = ?, @params = ?', [
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
                'message' => 'Error executing CV Upsert.',
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
            $result = DB::select('EXEC sproc_PHP_CV @mode = ?, @params = ?', [
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
                'message' => 'Error executing CV Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}


public function load(Request $request)
{
    $jsonData = $request->all();
    $jsonString = json_encode($jsonData);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_CV @mode = ?, @params = ?',
            ['Load', $jsonString]
        );

        $json = $results[0]->result ?? '[]';

        return response()->json([
            'success' => true,
            'data' => json_decode($json, true),
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

public function PostTransaction(Request $request)
{
    try {
        $inputData = $request->input('json_data'); // assuming "json_data" is the root key

        // Build the final payload exactly as expected by the stored procedure
        $params = [
            'json_data' => [
                'branchCode' => $inputData['branchCode'] ?? '',
                'apvNo' => $inputData['apvNo'] ?? '',
                'apvId' => $inputData['apvId'] ?? '',
                'apvDate' => $inputData['apvDate'] ?? now()->toDateString(),
                'apvtranType' => $inputData['apvtranType'] ?? 'APV01',
                'tranMode' => $inputData['tranMode'] ?? 'M',
                'apAcct' => $inputData['apAcct'] ?? '',
                'vendCode' => $inputData['vendCode'] ?? '',
                'vendName' => $inputData['vendName'] ?? '',
                'refapvNo1' => $inputData['refapvNo1'] ?? '',
                'refapvNo2' => $inputData['refapvNo2'] ?? '',
                'acctCode' => $inputData['acctCode'] ?? '',
                'currCode' => $inputData['currCode'] ?? 'PHP',
                'currRate' => $inputData['currRate'] ?? 1,
                'remarks' => $inputData['remarks'] ?? '',
                'userCode' => $inputData['userCode'] ?? 'SYSTEM',
                'dateStamp' => $inputData['dateStamp'] ?? now()->toISOString(),
                'timeStamp' => $inputData['timeStamp'] ?? '',
                'cutOff' => $inputData['cutOff'] ?? '',
                'tranDocId' => $inputData['tranDocId'] ?? '',
                'tranDocExist' => $inputData['tranDocExist'] ?? 0,
                'dt1' => $inputData['dt1'] ?? [],
                'dt2' => $inputData['dt2'] ?? []
            ]
        ];

        $jsonString = json_encode($params, JSON_UNESCAPED_UNICODE);

        // Optional: Log JSON payload for debug
        Log::debug('APV PostTransaction Payload:', ['json' => $jsonString]);

        // Execute DBCC TRACEON(460) before calling the stored procedure (helps with JSON parse error visibility)
        DB::statement('DBCC TRACEON(460)');

        // Call the stored procedure
        $result = DB::select("EXEC sproc_PHP_CV @mode = ?, @params = ?", ['Post', $jsonString]);

        $message = $result[0]->result ?? 'No result returned from stored procedure';

        if (str_starts_with($message, 'Error:')) {
            return response()->json([
                'success' => false,
                'message' => $message
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result
        ]);

    } catch (\Exception $e) {
        Log::error('PostTransaction Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to post transaction',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function posting(Request $request) {

    try {
        $results = DB::select(
            'EXEC sproc_PHP_CV @mode = ?',
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
            'json_data' => 'required|array'
        ]);
        // Already JSON, just assign
        // $params = json_encode($validated['json_data']);
        $params = json_encode(['json_data' => $validated['json_data']]);

          
        $results = DB::select(
            'EXEC sproc_PHP_Posting_CV @mode = ?, @params = ?',
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


public function history(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'History';

            // Call the stored procedure
            $results = DB::select('EXEC sproc_PHP_CV @mode = ?, @params = ?', [
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
                'message' => 'Error executing CV Upsert.',
                'details' => $e->getMessage()
            ], 500);
    }

}

}
