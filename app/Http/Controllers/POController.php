<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POController extends Controller
{
    
public function index(Request $request) {

    try {

        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');
      
        $results = DB::select(
            'EXEC sproc_PHP_PO @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_PO @mode = ?, @params = ?',
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
            $result = DB::select('EXEC sproc_PHP_PO @mode = ?, @params = ?', [
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
                'message' => 'Error executing PO Upsert.',
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
            $result = DB::select('EXEC sproc_PHP_PO @mode = ?, @params = ?', [
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
                'message' => 'Error executing PO Upsert.',
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
            $results = DB::select('EXEC sproc_PHP_PO @mode = ?, @params = ?', [
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
                'message' => 'Error executing JO Upsert.',
                'details' => $e->getMessage()
            ], 500);
    }

}

public function updatePrFromPo(Request $request)
{
    try {
        $branchCode = $request->input('branchCode');
        $poId       = $request->input('poId');
        $userCode   = $request->input('userCode');

        if (!$branchCode || !$poId) {
            return response()->json([
                'success' => false,
                'message' => 'branchCode and poId are required.'
            ], 400);
        }

        // Build JSON for sproc
        $params = json_encode([
            'json_data' => [
                'branchCode' => $branchCode,
                'poId'       => $poId,
                'userCode'   => $userCode
            ]
        ], JSON_UNESCAPED_UNICODE);

        // Call the UPDATE mode in sproc_PHP_PO
        DB::connection('tenant')->select(
            'EXEC sproc_PHP_PO @mode = ?, @params = ?',
            ['Update', $params]
        );

        return response()->json([
            'success' => true,
            'message' => 'PR quantities and statuses updated successfully.'
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update PR from PO.',
            'error'   => $e->getMessage()
        ], 500);
    }
}

public function getPOOpen(Request $request)
{
    Log::info('getPOOpen request', $request->all());

    $mode = $request->input('mode', 'Header');

    $rules = [
        'mode'       => 'required|string|in:Header,Detail',
        'branchCode' => 'nullable|string|max:10',
        'poTranType' => 'nullable|string|max:10',
        'poId'       => 'nullable|string|max:40',
    ];

    if ($mode === 'Detail') {
        $rules['poId'] = 'required|string|max:40';
    }

    $data = $request->validate($rules);

    $mode       = $data['mode'];
    $branchCode = $data['branchCode'] ?? null;
    $poTranType = $data['poTranType'] ?? null;
    $poId       = $data['poId'] ?? null;

    try {
        Log::info('getPOOpen calling sproc', [
            'mode'       => $mode,
            'branchCode' => $branchCode,
            'poTranType' => $poTranType,
            'poId'       => $poId,
        ]);

        $rows = DB::select(
            'EXEC sproc_PHP_PO_Open @mode = ?, @branchCode = ?, @poTranType = ?, @poId = ?',
            [$mode, $branchCode, $poTranType, $poId]
        );

        // Convert stdClass[] to array[]
        $rows = array_map(fn($r) => (array) $r, $rows);

        // Normalize numeric strings to numbers (Detail mode has lots of decimals)
        $numericKeys = [
            'PO_QUANTITY',
            'UOM_QTY2',
            'UNIT_COST',
            'FX_AMOUNT',
            'GROSS_AMOUNT',
            'DISC_RATE',
            'DISC_AMOUNT',
            'NET_AMOUNT',
            'VAT_AMOUNT',
            'ITEM_AMOUNT',
            'RR_QTY',
            'PR_BALANCE',
            'QTY_BALANCE', // if you added this in sproc
        ];

        if ($mode === 'Detail') {
            foreach ($rows as &$row) {
                foreach ($numericKeys as $k) {
                    if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                        // cast to float; FE can format as needed
                        $row[$k] = (float) $row[$k];
                    }
                }

                // Make sure GUID stays string (SQL sometimes returns it as a binary-ish object depending on driver)
                if (isset($row['PO_ID'])) $row['PO_ID'] = (string) $row['PO_ID'];

                // Optional: Normalize date string fields to YYYY-MM-DD for FE inputs
                foreach (['PO_DATE','DEL_DATE'] as $dk) {
                    if (!empty($row[$dk])) {
                        $row[$dk] = substr((string)$row[$dk], 0, 10);
                    }
                }
            }
            unset($row);
        } else {
            // Header mode: keep PoId string + shorten dates if present
            foreach ($rows as &$row) {
                if (isset($row['PoId'])) $row['PoId'] = (string) $row['PoId'];
                foreach (['PoDate','DelDate','DateStamp'] as $dk) {
                    if (!empty($row[$dk])) {
                        $row[$dk] = substr((string)$row[$dk], 0, 10);
                    }
                }
            }
            unset($row);
        }

        return response()->json([
            'success' => true,
            'mode'    => $mode,
            'count'   => count($rows),
            'data'    => $rows,
        ], 200);

    } catch (\Throwable $e) {
        Log::error('getPOOpen failed', [
            'error' => $e->getMessage(),
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
        ]);

        return response()->json([
            'success' => false,
            'mode'    => $mode,
            'message' => $e->getMessage(),
        ], 500);
    }
}

public function getPORR_OpenSummary(Request $request) {

   $jsonString = $request->input('PARAMS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PO @mode = ?, @params = ?',
            ['getPORR_OpenSummary' ,$jsonString] 
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

public function getFGPORR_OpenSummary(Request $request) {

   $jsonString = $request->input('PARAMS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PO @mode = ?, @params = ?',
            ['getFGPORR_OpenSummary' ,$jsonString] 
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




public function getPORR_OpenDetail(Request $request) {
    
    $jsonString = $request->input('json_data');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PO @mode = ?, @params = ?',
            ['getPORR_OpenDetail' ,$jsonString] 
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








