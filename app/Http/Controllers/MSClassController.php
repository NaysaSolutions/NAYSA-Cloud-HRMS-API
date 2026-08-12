<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MSClassController extends Controller
{

    public function index(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_MSClass @mode = ?',
                ['Load']
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
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
            'PARAMS' => 'required|string',
        ]);

        $params = $request->input('PARAMS');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_MSClass @mode = ?, @params = ?',
                ['Lookup', $params]
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
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
        $request->validate([
            'CLASS_CODE' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_MSClass @mode = ?, @params = ?',
                ['Get', $request->CLASS_CODE]
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
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
        $request->validate([
            'json_data' => 'required|json',
        ]);

        try {
            $params = $request->input('json_data');

            $rows = DB::select(
                'EXEC sproc_PHP_MSClass @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            $r0         = $rows[0] ?? null;
            $errorcount = (int)($r0->errorcount ?? 0);
            $errormsg   = (string)($r0->errormsg ?? '');

            if ($errorcount > 0) {
                return response()->json([
                    'success'    => false,
                    'errorcount' => $errorcount,
                    'errormsg'   => $errormsg,
                    'data'       => $rows,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction saved successfully.',
                'data'    => $rows,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Upsert failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save transaction: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function checkInUsed(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array',
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_MSClass @mode = ?, @params = ?',
                ['CheckInUsed', $params]
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array',
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_MSClass @mode = ?, @params = ?',
                ['CheckDuplicate', $params]
            );

            return response()->json([
                'success' => true,
                'data'    => $results,
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
        $request->validate([
            'json_data' => 'required|array',
        ]);

        $data = $request->json_data;
        $code = $data['code'] ?? null;

        if (!$code) {
            return response()->json([
                'success' => false,
                'message' => 'Classification code is required.',
            ], 400);
        }

        try {
            $params = json_encode([
                'json_data' => $data,
            ]);

            DB::statement(
                'EXEC sproc_PHP_MSClass @mode = ?, @params = ?',
                ['Delete', $params]
            );

            return response()->json([
                'success' => true,
                'message' => 'Deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}