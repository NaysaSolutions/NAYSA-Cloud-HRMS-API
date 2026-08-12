<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CurrController extends Controller
{

    public function index(Request $request)
    {

        try {
            $results = DB::select(
                'EXEC sproc_PHP_CurrRef @mode = ?',
                ['Load']
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






    public function lookup(Request $request)
    {

        $request->validate([
            'PARAMS' => 'required|string',
        ]);

        $params = $request->input('PARAMS');


        try {
            $results = DB::select(
                'EXEC sproc_PHP_CurrRef @mode = ?, @params = ?',
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










    public function get(Request $request)
    {

        $request->validate([
            'CURR_CODE' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_CurrRef @mode = ?, @params = ?',
                ['Get', $request->CURR_CODE]
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
    $data = $request->json_data; // Array from React

    try {
        $params = json_encode(['json_data' => $data]);

        // Use select to get the result set (errormsg, errorcount)
        $result = DB::select('EXEC sproc_PHP_CurrRef @mode = ?, @params = ?', ['Upsert', $params]);
        
        // Return the first row of the result directly to React
        return response()->json([
            'errormsg'   => $result[0]->errormsg ?? '',
            'errorcount' => $result[0]->errorcount ?? 0,
        ]);

    } catch (\Exception $e) {
        return response()->json(['errormsg' => $e->getMessage(), 'errorcount' => 1], 500);
    }
}






    public function checkInUsed(Request $request) {
    $validated = $request->validate([
        'json_data' => 'required|array',
        'json_data.currCode' => 'required|string',
    ]);

    $params = json_encode($validated);

    try {
        $results = DB::select('EXEC sproc_PHP_CurrRef @mode = ?, @params = ?', ['CheckInUsed', $params]);

        // Decode the internal JSON string from SQL for a cleaner API response
        $raw = $results[0]->result ?? '{"result":"0"}';
        $decoded = json_decode($raw, true);

        return response()->json([
            'success' => true,
            'isInUsed' => ($decoded['result'] ?? "0") === "1",
        ], 200);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}






public function checkDuplicate(Request $request)
{
    $validated = $request->validate([
        'json_data' => 'required|array',
        'json_data.currCode' => 'required|string',
    ]);

    $params = json_encode($validated); // Just encode the validated array

    try {
        $results = DB::select('EXEC sproc_PHP_CurrRef @mode = ?, @params = ?', ['CheckDuplicate', $params]);

        // Access the 'result' column we aliased in the Sproc
        $raw = $results[0]->result ?? '{"result":"0"}';
        $decoded = json_decode($raw, true);

        return response()->json([
            'success' => true,
            'result' => $decoded['result'] ?? "0",
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}



 public function delete(Request $request)
{
    $request->validate([
        'json_data' => 'required|array',
    ]);

    $data = $request->json_data;
    $code = $data['currCode'] ?? null;

    if (!$code) {
        return response()->json(['success' => false, 'message' => 'Code is required.'], 400);
    }

    try {
        // Wrap the array into the structure the SPROC expects
        $params = json_encode(['json_data' => $data]);

        // Use DB::select to get the row returned by the SPROC
        $result = DB::select('EXEC sproc_PHP_CurrRef @mode = ?, @params = ?', ['Delete', $params]);
        
        $errorCount = $result[0]->errorcount ?? 0;
        $errorMsg = $result[0]->errormsg ?? 'Unknown error';

        if ($errorCount > 0) {
            return response()->json([
                'success' => false,
                'message' => $errorMsg
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully.'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
}