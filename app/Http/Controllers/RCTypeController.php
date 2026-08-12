<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RCTypeController extends Controller
{
    /**
     * Load all RC Types
     */
    public function index(Request $request)
    {
        try {
            $results = DB::select('EXEC sproc_PHP_RCTypeRef @mode = ?', ['Load']);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add or Update RC Type (POST - already JSON)
     */
    public function upsert(Request $request)
    {
        try {
            // React already sends json_data as a stringified JSON
            $params = $request->input('json_data');

            $results = DB::select('EXEC sproc_PHP_RCTypeRef @mode = :mode, @params = :json_data', [
                'mode' => 'Upsert',
                'json_data' => $params,
            ]);

            return response()->json(['status' => 'success', 'data' => $results], 200);
        } catch (\Exception $e) {
            Log::error('Upsert failed:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to save: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete RC Type (POST - already JSON)
     */
    public function delete(Request $request)
    {
        try {
            $params = $request->input('json_data');
            
            $results = DB::select('EXEC sproc_PHP_RCTypeRef @mode = ?, @params = ?', ['Delete', $params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a single RC Type (GET - Convert to JSON)
     */
    public function get(Request $request)
    {
        try {
            $code = $request->query('rcTypeCode');
            
            // Format as JSON before sending to SPROC
            $json_params = json_encode(['rcTypeCode' => $code]);

            $results = DB::select('EXEC sproc_PHP_RCTypeRef @mode = ?, @params = ?', ['Get', $json_params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check Duplicate (POST - already JSON)
     */
    public function checkDuplicate(Request $request)
    {
        try {
            $params = $request->input('json_data');
            
            $results = DB::select('EXEC sproc_PHP_RCTypeRef @mode = ?, @params = ?', ['CheckDuplicate', $params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check In Used (POST - already JSON)
     */
    public function checkInUsed(Request $request)
    {
        try {
            $params = $request->input('json_data');
            
            $results = DB::select('EXEC sproc_PHP_RCTypeRef @mode = ?, @params = ?', ['CheckInUsed', $params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lookup for Dropdowns (GET - Convert to JSON)
     */
    public function lookup(Request $request)
    {
        try {
            $filter = $request->query('filter', '');
            
            // Format as JSON before sending to SPROC
            $json_params = json_encode(['filter' => $filter]);

            $results = DB::select('EXEC sproc_PHP_RCTypeRef @mode = ?, @params = ?', ['Lookup', $json_params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}