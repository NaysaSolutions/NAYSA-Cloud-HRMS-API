<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HSDocController extends Controller
{
    
public function get(Request $request) {

    $request->validate([
        'DOC_ID' => 'required|string',
    ]);

    $params = $request->input('DOC_ID');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_HSDoc @mode = ?, @docCode = ?',
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



public function lookup(Request $request)
{
    try {
        $results = DB::select(
            'EXEC sproc_PHP_HSDoc @mode = ?',
            ['Lookup']
        );

        return response()->json([
            'success' => true,
            'data' => $results, // no more `json_encode`
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

}
