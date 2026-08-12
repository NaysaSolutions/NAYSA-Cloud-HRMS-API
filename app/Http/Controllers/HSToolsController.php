<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;



class HSToolsController extends Controller
{
    

public function initialize(Request $request)
{
    $mode = $request->input('mode', $request->input('@mode'));
    $params = $request->input('params', $request->input('@params'));

    if (is_array($params)) {
        $params = json_encode($params);
    }

    try {
        $results = DB::select(
            'EXEC sproc_PHP_Initialize @mode = ?, @params = ?',
            [$mode, $params]
        );

        return response()->json([
            'success' => true,
            'message' => 'Initialize completed successfully.',
            'data' => $results,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}



public function getTblGetFieldLenght(Request $request) {

     $request->validate([
            'tableName' => 'required|string'
        ]);

        // Build the JSON structure exactly as the stored procedure expects
        $jsonData = [
            'json_data' => [
                'tableName' => $request->input('tableName')]
        ];

        $params = json_encode($jsonData);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_HSTools @mode = ?, @params = ?',
            ['TblGetFieldLenght' ,$params] 
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




    public function getDocTrail(Request $request) {
    // 1. Get the raw JSON and decode it
    $params = json_decode($request->input('PARAMS'), true);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_DocTrail 
                @_mode = "GetDocTrail" , 
                @_startdate = ?, 
                @_enddate = ?, 
                @_usercode = ?, 
                @_doccode = ?, 
                @_docNo =?,
                @_branchcode = ?',
            [
                $params['startDate'] ?? null,
                $params['endDate'] ?? null,
                $params['userCode'] ?? null,
                $params['docCode'] ?? null,
                $params['docNo'] ?? null,
                $params['branchCode'] ?? null
            ]
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



    
  public function getRefTrail(Request $request) {
    // 1. Get the raw JSON and decode it
    
    $params = json_decode($request->input('PARAMS'), true);

   
    try {
      
        $results = DB::select(
            'EXEC sproc_PHP_DocTrail 
                @_mode = "GetRefTrail", 
                @_startdate = ?, 
                @_enddate = ?, 
                @_usercode = ?, 
                @_tblCode = ?', 
            [
                $params['startDate'] ?? null,
                $params['endDate'] ?? null,
                $params['userCode'] ?? '',
                $params['refFile'] ?? ''
            ]
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


public function excelFileUpload(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
           

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_HSTools_ExcelFileUpload @params = ?', [
                $params
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing File Upload.',
                'details' => $e->getMessage()
            ], 500);
        }
}





}
