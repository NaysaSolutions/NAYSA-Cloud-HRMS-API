<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeGovInfo extends Controller
{
    //LOAD 
    public function index(Request $request) {
        try {
        $results = DB::select('EXEC sproc_PHP_Ref_EmployeeGovInfo @mode = ?',['Load']);
        
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

    // UPDATE
    public function upsert(Request $request){
        try {
        $request->validate(['json_data' => 'required|json',]);
        $params = $request->get('json_data');
        
        $results = DB::select('EXEC sproc_PHP_Ref_EmployeeGovInfo @params = :json_data, @mode = :mode', [
            'json_data' => $params,
            'mode' => 'Upsert'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ], 200);

        } catch (\Exception $e) {
        Log::error('Saving failed:', ['error' => $e->getMessage()]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
        }
    }


}
