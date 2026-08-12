<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class MSInvBalanceController extends Controller
{
  


public function getInvLookup(Request $request) {


    $jsonData = $request->all();
    $jsonString = json_encode($jsonData);


    try {

       $jsonString = $request->input('PARAMS');

       $results = DB::select(
            'EXEC sproc_PHP_INVLookup_MS @params =? ',
            [$jsonString] 
        );
        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()

        ], 500);
    }
}



}
