<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CutoffController extends Controller
{
    
public function index(Request $request) {

    try {
        $results = DB::select(
            'EXEC sproc_PHP_CutoffRef @mode = ?',
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

    $request->validate([
        'PARAMS' => 'required|string',
    ]);

    $params = $request->input('PARAMS');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_CutoffRef @mode = ?, @params = ?',
            ['Lookup' ,$params] 
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
        'CUTOFF_CODE' => 'required|string',
    ]);

    $params = $request->input('CUTOFF_CODE');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_CutoffRef @mode = ?, @params = ?',
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
            'json_data' => 'required',
        ]);

        $params = $request->input('json_data');

        if (is_array($params)) {
            $params = json_encode(['json_data' => $params]);
        }

        $results = DB::select(
            'EXEC sproc_PHP_CutoffRef @params = :json_data, @mode = :mode',
            [
                'json_data' => $params,
                'mode' => 'Upsert',
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ], 200);

    } catch (\Exception $e) {
        Log::error('Transaction save failed:', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
    }
}



public function delete(Request $request){

    try {

      $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);
      

        $results = DB::select(
            'EXEC sproc_PHP_CutoffRef @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_CutoffRef @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_CutoffRef @mode = ?, @params = ?',
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
