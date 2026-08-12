<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;



class PriceMatrixController extends Controller
{
    


public function get(Request $request) {

    $jsonData = $request->all(); 
    $jsonString = json_encode($jsonData); 

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?',
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


public function delete(Request $request)
{
    $jsonData = $request->all();
    $jsonString = json_encode($jsonData);

    try {
        DB::statement(
            'EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?',
            ['Delete', $jsonString]
        );

        return response()->json([
            'success' => true,
            'message' => 'Price matrix deleted successfully.',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}




public function deletePrio(Request $request)
{
    $jsonData = $request->all();
    $jsonString = json_encode($jsonData);

    try {
        DB::statement(
            'EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?',
            ['DeletePrio', $jsonString]
        );

        return response()->json([
            'success' => true,
            'message' => 'Price matrix deleted successfully.',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}




public function history(Request $request) {

    $jsonData = $request->all(); 
    $jsonString = json_encode($jsonData); 

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?',
            ['History' ,$jsonString] 
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



public function historyPerItem(Request $request) {

    $jsonData = $request->all(); 
    $jsonString = json_encode($jsonData); 

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?',
            ['HistoryPerItem' ,$jsonString] 
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
            $result = DB::select('EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?', [
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
                'message' => 'Error executing Price Matrix Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}





public function upsertPrio(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'UpsertPrio';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?', [
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
                'message' => 'Error executing Price Matrix Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}







public function getPrio(Request $request) {

    $jsonData = $request->all(); 
    $jsonString = json_encode($jsonData); 

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?',
            ['GetPrio' ,$jsonString] 
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








  
public function getItemPrice(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'GetItemPrice';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_PriceMatrix @mode = ?, @params = ?', [
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
                'message' => 'Error executing Price Matrix Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}



}
