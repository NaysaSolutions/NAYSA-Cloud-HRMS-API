<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AllBIRController extends Controller
{
    
   
        
    public function getEWTInquiry(Request $request)
    { 
        try {

        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);

        $results = DB::select(
                'EXEC sproc_PHP_AllBIR_Inq @_mode =?, @_params =? ',
                ['EWT_Inq', $jsonString] 
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


    
    public function getCWTInquiry(Request $request)
    { 
        try {

        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);

        $results = DB::select(
                'EXEC sproc_PHP_AllBIR_Inq @_mode =?, @_params =? ',
                ['CWT_Inq', $jsonString] 
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




    public function getINTAXInquiry(Request $request)
    { 
        try {

        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);

        $results = DB::select(
                'EXEC sproc_PHP_AllBIR_Inq @_mode =?, @_params =? ',
                ['INTAX_Inq', $jsonString] 
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




    public function getOUTAXInquiry(Request $request)
    { 
        try {

        $dataArray = $request->input('json_data');
        $jsonString = json_encode($dataArray);

        $results = DB::select(
                'EXEC sproc_PHP_AllBIR_Inq @_mode =?, @_params =? ',
                ['OUTAX_Inq', $jsonString] 
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



    
    
public function generateBIRBooks(Request $request)
{

    $params = $request->input('PARAMS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_AllBIR_Books @params = ?',
            [$params]
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
