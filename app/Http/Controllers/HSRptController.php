<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class HSRptController extends Controller
{


    public function get(Request $request) {

    $request->validate([
        'REPORT_ID' => 'required|string',
    ]);

    $params = $request->input('REPORT_ID');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_HSRpt @mode = ?, @reportId = ?',
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




public function index(Request $request) {

  
    $mdl = $request->input('mdl');
    $userCode = $request->input('userCode');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_HSRpt @mode = ?, @mdl = ?, @userCode = ?',
            ['load' ,$mdl ,$userCode] 
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
