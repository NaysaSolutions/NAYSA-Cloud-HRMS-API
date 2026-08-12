<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseMastController extends Controller
{
    public function upsert(Request $request)
    {
        try {
            $request->validate(['json_data' => 'required']);
            $params = $request->get('json_data');

            // Ensure params is a string for the sproc
            if (!is_string($params)) {
                $params = json_encode($params);
            }

            $results = DB::select('EXEC sproc_PHP_WareMast @params = ?, @mode = ?', [
                $params,
                'Upsert'
            ]);

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            Log::error('Warehouse Upsert failed:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function load(Request $request)
    {
        try {
            // Check for branchFilter from the request query
            $branchFilter = $request->query('branchFilter', '');
            
            // Construct the params JSON expected by the Sproc
            $params = json_encode([
                'json_data' => [
                    'branchFilter' => $branchFilter
                ]
            ]);

            $results = DB::select('EXEC sproc_PHP_WareMast @mode = ?, @params = ?', [
                'Load', 
                $params
            ]);

            $data = !empty($results) && isset($results[0]->result) 
                ? json_decode($results[0]->result) 
                : $results;

            return response()->json(['success' => true, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

public function checkInUsedWH(Request $request) {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_WareMast @mode = ?, @params = ?', 
                // CHANGE THIS: Must be lowercase 'c' to match the sproc exactly
                ['checkInUsed', $params] 
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

    // Add this missing method for your Duplicate Check
    public function checkDuplicateWH(Request $request) {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_WareMast @mode = ?, @params = ?', 
                ['CheckDuplicate', $params] 
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





    public function delete(Request $request)
    {
        try {
            $validated = $request->validate(['json_data' => 'required|array']);
            $params = json_encode(['json_data' => $validated['json_data']]);

            $results = DB::select('EXEC sproc_PHP_WareMast @mode = ?, @params = ?', [
                'Delete',
                $params
            ]);

            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    

    // Standard lookup if needed separately
    public function lookup(Request $request)
    {
        try {
            $filter = $request->query('filter', 'ActiveAll');
            $results = DB::select('EXEC sproc_PHP_WareMast @mode = ?, @params = ?', ['Lookup', $filter]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}