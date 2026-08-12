<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MSMastController extends Controller
{
   
public function index(Request $request) {

    try {
        $results = DB::select(
            'EXEC sproc_PHP_MSMast @mode = ?',
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
    $raw = $request->input('PARAMS');

    // Decode whatever the frontend sent
    $decoded = json_decode($raw, true);

    // If it's not a valid JSON object, start fresh
    if (!is_array($decoded)) {
        $decoded = [];
    }

    // Ensure required keys exist with safe defaults
    if (!array_key_exists('search', $decoded)) {
        $decoded['search'] = '';
    }
    if (!array_key_exists('searchMode', $decoded)) {
        $decoded['searchMode'] = 'contains';
    }
    if (!array_key_exists('filter', $decoded)) {
        $decoded['filter'] = 'ActiveAll';
    }

    $params = json_encode($decoded);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_MSMast @mode = ?, @params = ?',
            ['Lookup', $params]
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
        'ITEM_CODE' => 'required|string',
    ]);

    $params = $request->input('ITEM_CODE');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_MSMast @mode = ?, @params = ?',
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


//         DB::statement('EXEC sproc_PHP_MSMast @params = :json_data, @mode = :mode', [
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

public function upsert(Request $request)
{
    try {
        // Pass the full request body, not just the inner object
        $params = $request->all(); // gets {"json_data": {...}}

        if (is_array($params) || is_object($params)) {
            $params = json_encode($params);
        }

        if (!is_string($params) || !json_validate($params)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid JSON data provided.'
            ], 400);
        }

        $result = DB::select(
            'EXEC sproc_PHP_MSMast @params = :json_data, @mode = :mode',
            [
                'json_data' => $params,
                'mode'      => 'Upsert',
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Transaction saved successfully.',
            'data'    => $result,
        ], 200);

    } catch (\Exception $e) {
        Log::error('Transaction save failed:', ['error' => $e->getMessage()]);
        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
    }
}

public function checkDuplicate(Request $request)
{
    $validated = $request->validate([
        'json_data' => 'required|array'
    ]);

    $params = json_encode(['json_data' => $validated['json_data']]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_MSMast @mode = ?, @params = ?',
            ['CheckDuplicate', $params]
        );

        return response()->json([
            'success' => true,
            'data'    => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

public function checkInUsed(Request $request)
{
    $validated = $request->validate([
        'json_data' => 'required|array'
    ]);

    $params = json_encode(['json_data' => $validated['json_data']]);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_MSMast @mode = ?, @params = ?',
            ['CheckInUsed', $params]
        );

        return response()->json([
            'success' => true,
            'data'    => $results,
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
    $request->validate([
        'json_data' => 'required|array',
    ]);

    try {
        $params = json_encode(['json_data' => $request->json_data]);

        $results = DB::select(
            'EXEC sproc_PHP_MSMast @mode = ?, @params = ?',
            ['Delete', $params]
        );

        return response()->json([
            'success' => true,
            'data'    => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}


}
