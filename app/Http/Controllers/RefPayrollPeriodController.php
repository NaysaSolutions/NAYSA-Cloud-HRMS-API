<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefPayrollPeriodController extends Controller
{
    public function index()
    {
        $data = DB::select("EXEC sproc_PHP_Ref_Cutoff @mode='Load'");
        return response()->json(['data' => $data]);
    }

    public function get(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Cutoff @mode='Get', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function upsert(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Cutoff @mode='Upsert', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function delete(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Cutoff @mode='Delete', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function checkDuplicate(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Cutoff @mode='CheckDuplicate', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function checkInUsed(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Cutoff @mode='CheckInUsed', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function lookup(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_Ref_Cutoff @mode='Lookup', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }
}