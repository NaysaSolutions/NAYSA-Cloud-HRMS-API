<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function index()
    {
        $data = DB::select("EXEC sproc_PHP_Ref_Shift @mode='Load'");
        return response()->json(['data' => $data]);
    }

    public function upsert(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Shift @mode='Upsert', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function delete(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Shift @mode='Delete', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function checkDuplicate(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Shift @mode='CheckDuplicate', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function checkInUsed(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Shift @mode='CheckInUsed', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }
}