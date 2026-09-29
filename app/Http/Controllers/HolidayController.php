<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        try {
            $results = DB::connection('tenant')->select('EXEC sproc_PHP_Ref_Holiday @mode = ?', ['Load']);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function lookup(Request $request)
    {
        $request->validate(['PARAMS' => 'required']);
        $params = $request->input('PARAMS');
        try {
            $results = DB::connection('tenant')->select('EXEC sproc_PHP_Ref_Holiday @mode = ?, @params = ?', ['Lookup', $params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function get(Request $request)
    {
        $request->validate(['HOL_CODE' => 'required|string']);
        try {
            $results = DB::connection('tenant')->select('EXEC sproc_PHP_Ref_Holiday @mode = ?, @params = ?', ['Get', $request->input('HOL_CODE')]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        try {
            $request->validate(['json_data' => 'required|json']);
            $results = DB::connection('tenant')->select(
                'EXEC sproc_PHP_Ref_Holiday @params = :json_data, @mode = :mode',
                ['json_data' => $request->get('json_data'), 'mode' => 'Upsert']
            );
            return response()->json(['status' => 'success', 'data' => $results], 200);
        } catch (\Exception $e) {
            Log::error('Saving Holiday failed:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to save Holiday: ' . $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $validated = $request->validate(['json_data' => 'required|array']);
            $params = json_encode(['json_data' => $validated['json_data']]);
            $results = DB::connection('tenant')->select('EXEC sproc_PHP_Ref_Holiday @mode = ?, @params = ?', ['Delete', $params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function checkInUsed(Request $request)
    {
        $validated = $request->validate(['json_data' => 'required|array']);
        $params = json_encode(['json_data' => $validated['json_data']]);
        try {
            $results = DB::connection('tenant')->select('EXEC sproc_PHP_Ref_Holiday @mode = ?, @params = ?', ['CheckInUsed', $params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate(['json_data' => 'required|array']);
        $params = json_encode(['json_data' => $validated['json_data']]);
        try {
            $results = DB::connection('tenant')->select('EXEC sproc_PHP_Ref_Holiday @mode = ?, @params = ?', ['CheckDuplicate', $params]);
            return response()->json(['success' => true, 'data' => $results], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
