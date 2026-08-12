<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class HSDropdownController extends Controller
{
    


public function get(Request $request) {


    $jsonData = $request->all();
    $jsonString = json_encode($jsonData);


    try {
        $results = DB::select(
            'EXEC sproc_PHP_HSDropdown @mode = ?, @params = ?',
            ['get' ,$jsonString] 
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




public function getAll(Request $request) {


    try {
        $results = DB::select(
            'EXEC sproc_PHP_HSDropdown @mode = ?',
            ['getAll' ] 
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
