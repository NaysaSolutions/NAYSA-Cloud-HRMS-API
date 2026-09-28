<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GovPHController extends Controller
{
    public function index(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_Ref_GovPH @mode = ?',
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
            'params' => 'required|json',
        ]);

        $params = $request->input('params');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_Ref_GovPH @mode = ?, @params = ?',
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
        $validated = $request->validate([
            'orderNo' => 'required|string|max:3',
        ]);

        $params = json_encode(['jsonData' => $validated]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_Ref_GovPH @mode = ?, @params = ?',
                ['Get', $params]
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
        try {
            $request->validate([
                'jsonData' => 'required|json',
            ]);

            $params = $request->get('jsonData');

            $results = DB::select('EXEC sproc_PHP_Ref_GovPH @params = :jsonData, @mode = :mode', [
                'jsonData' => $params,
                'mode' => 'Upsert',
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
