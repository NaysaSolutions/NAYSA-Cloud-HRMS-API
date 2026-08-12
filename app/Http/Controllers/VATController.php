<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class VATController extends Controller
{
  
public function index(Request $request) {

    try {
        $results = DB::select(
            'EXEC sproc_PHP_VATRef @mode = ?',
            ['Load' ] 
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


   



public function lookup(Request $request) {

  
    $paramsString = $request->input('PARAMS');
    $params = json_decode($paramsString, true);
   

    try {
        $results = DB::select(
            'EXEC sproc_PHP_VATRef @mode = ?, @params = ?',
            ['Lookup' ,$params['search']] 
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

    $request->validate([
        'VAT_CODE' => 'required|string',
    ]);

    $params = $request->input('VAT_CODE');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_VATRef @mode = ?, @params = ?',
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




public function upsert(Request $request)
{
    try {
        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');

        // 1. Use DB::select to capture the Sproc's output (errormsg, errorcount)
        $results = DB::select('EXEC sproc_PHP_VATRef @params = :json_data, @mode = :mode', [
            'json_data' => $params,
            'mode' => 'upsert'
        ]);

        // 2. Return the SQL results so React can read them
        return response()->json([
            'status' => 'success',
            'data' => $results, // <--- This contains your validation table
        ], 200);

    } catch (\Exception $e) {
        Log::error('Saving failed:', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
    }
}












// public function upsert(Request $request)
// {
//     try {
//         $request->validate([
//             'json_data' => 'required|json',
//         ]);

//         $params = $request->get('json_data');

      

//         if (json_last_error() !== JSON_ERROR_NONE) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Invalid JSON data provided.',
//             ], 400);
//         }


//         DB::statement('EXEC sproc_PHP_VATRef @params = :json_data, @mode = :mode', [
//             'json_data' => $params,
//             'mode' => 'upsert'
//         ]);


//         return response()->json([
//             'status' => 'success',
//             'message' => 'Transaction saved successfully.',
//         ], 200);
//     } catch (\Exception $e) {
//         Log::error('Transaction save failed:', ['error' => $e->getMessage()]);

//         return response()->json([
//             'status' => 'error',
//             'message' => 'Failed to save transaction: ' . $e->getMessage(),
//         ], 500);
//     }
// }

public function delete(Request $request){

    try {

      $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);
      

        $results = DB::select(
            'EXEC sproc_PHP_VATRef @mode = ?, @params = ?',
            ['Delete', $params]
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


public function checkInUsed(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_VATRef @mode = ?, @params = ?',
            ['CheckInUsed' ,$params] 
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






public function checkDuplicate(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_VATRef @mode = ?, @params = ?',
            ['CheckDuplicate' ,$params] 
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
