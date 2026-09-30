<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefEDController extends Controller
{
    public function index()
    {
        $data = DB::select("EXEC sproc_PHP_REF_ED @mode='Load'");
        return response()->json(['data' => $data]);
    }

    public function upsert(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_REF_ED @mode='Upsert', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function delete(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_REF_ED @mode='Delete', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function checkDuplicate(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_REF_ED @mode='CheckDuplicate', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function checkInUsed(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_REF_ED @mode='CheckInUsed', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }

    public function lookup(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_REF_ED @mode='Lookup', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }
    
    public function get(Request $request)
    {
        $params = json_encode($request->all());
        $data = DB::select("EXEC sproc_PHP_REF_ED @mode='Get', @params=?", [$params]);
        return response()->json(['data' => $data]);
    }
}