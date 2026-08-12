<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ARBalanceController extends Controller
{

    
public function getOpenARBalance(Request $request)
    { 
     try {

       $jsonString = $request->input('PARAMS');

       $results = DB::select(
            'EXEC sproc_PHP_ARDTL @_mode =?, @_params =? ',
            ['OpenARBalance', $jsonString] 
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



    

public function getSelectedARBalance(Request $request)
    { 
     try {

        $jsonData = $request->input('json_data');
           
        $results = DB::select(
            'EXEC sproc_PHP_ARDTL @_mode =?, @_params =? ',
            ['getSelectedARBalance', $jsonData] 
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






    
public function getARInquiry(Request $request)
    { 
     try {

        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);
           

        $results = DB::select(
            'EXEC sproc_PHP_AR_Inq @_mode =?, @_params =? ',
            ['AR_Inq', $jsonString] 
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




    
public function getARAging(Request $request)
    { 
     try {

        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);
           
        $results = DB::select(
            'EXEC sproc_PHP_AR_Inq @_mode =?, @_params =? ',
            ['AR_Aging', $jsonString] 
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





    
public function getARAdvances(Request $request)
    { 
     try {
        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);
           
        $results = DB::select(
            'EXEC sproc_PHP_AR_Inq @_mode =?, @_params =? ',
            ['AR_Advances', $jsonString] 
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












public function getARCWLCLInquiry(Request $request)
    { 
     try {
        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);
           
        $results = DB::select(
            'EXEC sproc_PHP_AR_CWT @_mode =?, @_params =? ',
            ['Load_ARCLCWT', $jsonString] 
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






    
public function updateARCWLCL(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Update_ARCLCWT';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_AR_CWT @_mode = ?, @_params = ?', [
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
                'message' => 'Error executing AR Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}







public function generateJVARCWLCL(Request $request) {

    try {

       $validated = $request->validate([
            'json_data' => 'required|array'
        ]);
        // Already JSON, just assign
        // $params = json_encode($validated['json_data']);
        $params = json_encode(['json_data' => $validated['json_data']]);

          
        $results = DB::select(
            'EXEC sproc_PHP_AR_CWT @_mode = ?, @_params = ?',
            ['GenerateJV_ARCLCWT', $params]
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




