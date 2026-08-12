<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FGRRController extends Controller
{
    
public function index(Request $request) {

    try {

        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');
      
        $results = DB::select(
            'EXEC sproc_PHP_FGRR @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_FGRR @mode = ?, @params = ?',
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

        $result = DB::select(
            "EXEC sproc_PHP_FGRR @mode = ?, @params = ?",
            ["Upsert", $params]
        );

        // Return same response structure as MSAJ
        return response()->json([
            'success' => true,
            'mode'    => 'Upsert',
            'data'    => $result
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error executing FGRR Upsert.',
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
            $result = DB::select('EXEC sproc_PHP_FGRR @mode = ?, @params = ?', [
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
                'message' => 'Error executing FGRR Cancel.',
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
            $results = DB::select('EXEC sproc_PHP_FGRR @mode = ?, @params = ?', [
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
                'message' => 'Error executing FGRR History.',
                'details' => $e->getMessage()
            ], 500);
    }

}

public function getPROpen(Request $request)
{
    Log::info('getPROpen request', $request->all());

    $mode = $request->input('mode', 'Header');

    $rules = [
        'mode'       => 'required|string|in:Header,Detail',
        'branchCode' => 'nullable|string|max:10',
        'prTranType' => 'nullable|string|max:10',
    ];

    if ($mode === 'Detail') {
        // 👇 treat prId as string (GUID)
        $rules['prId'] = 'required|string|max:40';
    } else {
        $rules['prId'] = 'nullable|string|max:40';
    }

    $data = $request->validate($rules);

    $mode       = $data['mode'];
    $branchCode = $data['branchCode'] ?? null;
    $prTranType = $data['prTranType'] ?? null;
    $prId       = $data['prId'] ?? null;

    try {
        Log::info('getPROpen calling sproc', [
            'mode'       => $mode,
            'branchCode' => $branchCode,
            'prTranType' => $prTranType,
            'prId'       => $prId,
        ]);

        $rows = DB::select(
            'EXEC sproc_PHP_PR_Open @mode = ?, @branchCode = ?, @prTranType = ?, @prId = ?',
            [$mode, $branchCode, $prTranType, $prId]
        );

        return response()->json([
            'success' => true,
            'data'    => $rows,
        ], 200);
    } catch (\Throwable $e) {
        Log::error('getPROpen failed', [
            'error' => $e->getMessage(),
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

public function update(Request $request)
    {
        try {
            $branchCode = $request->input('branchCode');   // e.g. "HO"
            $poId       = $request->input('poId');         // PO_ID (GUID)
            $userCode   = $request->user()->USER_CODE
                        ?? $request->input('userCode', 'NSI');

            if (!$branchCode || !$poId) {
                return response()->json([
                    'success' => false,
                    'message' => 'branchCode and poId are required.',
                ], 422);
            }

            // Build the JSON payload expected by your sproc
            $payload = json_encode([
                'json_data' => [
                    'branchCode' => $branchCode,
                    'poId'       => $poId,
                    'userCode'   => $userCode,
                ],
            ]);

            // Call sproc_PHP_PO with mode = 'Update'
            $result = DB::connection('sqlsrv')->select(
                "EXEC sproc_PHP_PO @mode = :mode, @params = :params",
                [
                    'mode'   => 'Update',
                    'params' => $payload,
                ]
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function finalize(Request $request)
{
    try {

        Log::info('🟠 FGRR FINALIZE RAW REQUEST', [
            'all' => $request->all()
        ]);

        Log::info('🟠 FGRR json_data ONLY', [
            'json_data' => $request->input('json_data')
        ]);

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode([
            'json_data' => $validated['json_data']
        ]);

        Log::info('🟠 FGRR FINAL PAYLOAD TO SQL', [
            'params' => $params
        ]);

        $results = DB::select(
            'EXEC sproc_PHP_Posting_FGRR @mode = ?, @params = ?, @userCode = ?',
            ['Finalize', $params, $request->user()->USER_CODE ?? 'ADMIN']
        );

        Log::info('🟠 FGRR SQL RESULT', [
            'result' => $results
        ]);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);

    } catch (\Exception $e) {

        Log::error('❌ FGRR FINALIZE ERROR', [
            'message' => $e->getMessage()
        ]);

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


            $results = DB::select("EXEC sproc_PHP_FGRR @mode = ?, @params = ?", [
                'GenerateEntries',
                $jsonString
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing sproc_PHP_FGRR: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate entries.',
                'details' => $e->getMessage()
            ], 500);
        }
    }



    public function find(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Find';

            $results = DB::select(
                'EXEC sproc_PHP_FGRR @mode = ?, @params = ?',
                [$mode, $params]
            );

            return response()->json([
                'status' => 'success',
                'data' => $results
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error executing sproc_PHP_FGRR Find: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Error executing FGRR Find.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function posting(Request $request)
{
    try {

        $results = DB::select(
            'EXEC sproc_PHP_FGRR @mode = ?',
            ['Posting']
        );

        $decoded = [];

        if (!empty($results) && isset($results[0]->result)) {
            $decoded = json_decode($results[0]->result, true) ?? [];
        }

        return response()->json([
            'success' => true,
            'data' => $decoded,
        ], 200);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}



}








