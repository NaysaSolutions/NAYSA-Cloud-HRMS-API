<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class APBalanceController extends Controller
{

    
public function getOpenAPBalance(Request $request)
    { 
     try {

       $jsonString = $request->input('PARAMS');

       $results = DB::select(
            'EXEC sproc_PHP_APDTL @_mode =?, @_params =? ',
            ['OpenAPBalance', $jsonString] 
        );
        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()

        ], 500);
    }
    }



    

public function getSelectedAPBalance(Request $request)
    { 
     try {

        $jsonData = $request->input('json_data');
           
        $results = DB::select(
            'EXEC sproc_PHP_APDTL @_mode =?, @_params =? ',
            ['getSelectedAPBalance', $jsonData]
        );
        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()

        ], 500);
    }
    }




public function getAPInquiry(Request $request)
    { 
     try {

        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);
           

        $results = DB::select(
            'EXEC sproc_PHP_AP_Inq @_mode =?, @_params =? ',
            ['AP_Inq', $jsonString] 
        );
        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()

        ], 500);
    }
    }




    
public function getAPAging(Request $request)
    { 
     try {

        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);
           
        $results = DB::select(
            'EXEC sproc_PHP_AP_Inq @_mode =?, @_params =? ',
            ['AP_Aging', $jsonString] 
        );
        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()

        ], 500);
    }
    }





    
public function getAPAdvances(Request $request)
    { 
     try {
        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);
           
        $results = DB::select(
            'EXEC sproc_PHP_AP_Inq @_mode =?, @_params =? ',
            ['AP_Advances', $jsonString] 
        );
        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()

        ], 500);
    }
    }






public function getAPCheckRelasing(Request $request)
    { 
     try {
        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);
           
        $results = DB::select(
            'EXEC sproc_PHP_CK_RL @_mode =?, @_params =? ',
            ['Load_CKRL', $jsonString] 
        );
        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()

        ], 500);
    }
    }






    
public function updateAPCKRL(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Update_CKRL';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_CK_RL @_mode = ?, @_params = ?', [
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
                'message' => 'Error executing Check Releasing Update',
                'details' => $e->getMessage()
            ], 500);
        }
}





}
