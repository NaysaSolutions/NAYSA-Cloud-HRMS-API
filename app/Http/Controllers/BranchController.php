<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class BranchController extends Controller
{
  
public function index(Request $request) {

    try {
        $results = DB::select(
            'EXEC sproc_PHP_BranchRef @mode = ?',
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
        'PARAMS' => 'required',
    ]);

    $params = $request->input('PARAMS');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_BranchRef @mode = ?, @params = ?',
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
        'BRANCH_CODE' => 'required|string',
    ]);

    $params = $request->input('BRANCH_CODE');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_BranchRef @mode = ?, @params = ?',
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




// public function upsert(Request $request)
// {
//     try {
//         // Expect an associative array for json_data
//         $request->validate([
//             'json_data' => 'required|array',
//         ]);

//         // Get the payload the client sent
//         $jsonData = $request->input('json_data');

//         // If the client already wrapped it, keep it; otherwise wrap it
//         $toSend = array_key_exists('json_data', $jsonData)
//             ? $jsonData
//             : ['json_data' => $jsonData];

//         $params = json_encode($toSend);

//         Log::info('Upsert BranchRef params:', ['params' => $params]);

//         DB::statement(
//             'EXEC sproc_PHP_BranchRef @params = :params, @mode = :mode',
//             [
//                 'params' => $params,
//                 'mode'   => 'upsert',
//             ]
//         );

//         return response()->json([
//             'status'  => 'success',
//             'message' => 'Transaction saved successfully.',
//         ], 200);

//     } catch (\Exception $e) {
//         Log::error('Transaction save failed:', ['error' => $e->getMessage()]);

//         return response()->json([
//             'status'  => 'error',
//             'message' => 'Failed to save transaction: ' . $e->getMessage(),
//         ], 500);
//     }
// }


public function upsert(Request $request)
{
    try {
        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');

        // 1. Use DB::select to capture the Sproc's output (errormsg, errorcount)
        $results = DB::select('EXEC sproc_PHP_BranchRef @params = :json_data, @mode = :mode', [
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


// public function delete(Request $request)
// {
//     try {
//         $request->validate([
//             'json_data' => 'required|array',
//         ]);

//         $jsonData = $request->get('json_data');

//         // Convert to JSON string
//         $params = json_encode(['json_data' => $jsonData]);

//         Log::info('Deleting branch with params:', ['params' => $params]);

//         // Call stored procedure
//         DB::statement('EXEC sproc_PHP_BranchRef @params = :params, @mode = :mode', [
//             'params' => $params,
//             'mode' => 'Delete'
//         ]);

//         return response()->json([
//             'status' => 'success',
//             'message' => 'Branch deleted successfully.',
//         ], 200);

//     } catch (\Exception $e) {
//         Log::error('Branch deletion failed:', ['error' => $e->getMessage()]);

//         return response()->json([
//             'status' => 'error',
//             'message' => 'Failed to delete branch: ' . $e->getMessage(),
//         ], 500);
//     }
// }


public function delete(Request $request) {

    try {

      $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);
      

        $results = DB::select(
            'EXEC sproc_PHP_BranchRef @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_BranchRef @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_BranchRef @mode = ?, @params = ?',
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
