<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QStatController extends Controller
{
    /**
     * LOAD (list)
     * GET /qstat
     */
    public function index(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_QStatRef @mode = ?',
                ['Load']
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);

        } catch (\Exception $e) {

            Log::error('QSTAT Load failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    

public function lookup(Request $request) {

    $request->validate([
        'PARAMS' => 'required|string',
    ]);

    $params = $request->input('PARAMS');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_QStatRef @mode = ?, @params = ?',
            ['Lookup' ,$params] 
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


    /**
     * GET single record
     * GET /getQStat
     */
    public function get(Request $request)
    {
        $request->validate([
            'QSTAT_CODE' => 'required|string',
        ]);

        $code = $request->input('QSTAT_CODE');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_QStatRef @mode = ?, @params = ?',
                ['Get', $code]
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);

        } catch (\Exception $e) {

            Log::error('QSTAT Get failed:', [
                'error'      => $e->getMessage(),
                'QSTAT_CODE' => $code
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * UPSERT
     * POST /upsertQStat
     */
    public function upsert(Request $request)
    {
        $request->validate([
            'json_data' => 'required|array',
        ]);

        try {
            $jsonData = $request->input('json_data');

            $params = json_encode([
                'json_data' => $jsonData
            ]);

            DB::statement(
                'EXEC sproc_PHP_QStatRef @params = :params, @mode = :mode',
                [
                    'params' => $params,
                    'mode'   => 'Upsert',
                ]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Transaction saved successfully.',
            ], 200);

        } catch (\Exception $e) {

            Log::error('QSTAT Upsert failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to save transaction: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE
     * POST /deleteQStat
     */
    public function delete(Request $request)
    {
        $request->validate([
            'json_data' => 'required|array',
        ]);

        try {
            $jsonData = $request->input('json_data');

            $params = json_encode([
                'json_data' => $jsonData
            ]);

            DB::statement(
                'EXEC sproc_PHP_QStatRef @params = :params, @mode = :mode',
                [
                    'params' => $params,
                    'mode'   => 'Delete',
                ]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Record deleted successfully.',
            ], 200);

        } catch (\Exception $e) {

            Log::error('QSTAT Delete failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete record: ' . $e->getMessage(),
            ], 500);
        }
    }



public function checkInUsed(Request $request) {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_QStatRef @mode = ?, @params = ?', // FIX: Changed from sproc_PHP_Uom
                ['CheckInUsed', $params] 
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

    public function checkDuplicate(Request $request) {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_QStatRef @mode = ?, @params = ?', // FIX: Changed from sproc_PHP_Uom
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


}
