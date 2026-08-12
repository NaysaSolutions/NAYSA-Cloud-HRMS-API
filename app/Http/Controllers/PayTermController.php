<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PayTermController extends Controller
{
    public function index(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_PayTermRef @mode = ?',
                ['Load']
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PayTerm index failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // public function lookup(Request $request)
    // {
    //     $request->validate([
    //         'PARAMS' => 'required|string',
    //     ]);

    //     $params = $request->input('PARAMS');

    //     try {
    //         $results = DB::select(
    //             'EXEC sproc_PHP_PayTermRef @mode = ?, @params = ?',
    //             ['lookup', $params]
    //         );

    //         return response()->json([
    //             'success' => true,
    //             'data' => $results,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         Log::error('PayTerm lookup failed:', ['error' => $e->getMessage()]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function lookup(Request $request)
{
    $params = $request->query('PARAMS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PayTermRef @mode = ?, @params = ?',
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
        $request->validate([
            'PAYTERM_CODE' => 'nullable|string',
            'paytermCode' => 'nullable|string',
        ]);

        $params = $request->input('PAYTERM_CODE') ?? $request->input('paytermCode');

        if (!$params) {
            return response()->json([
                'success' => false,
                'message' => 'Pay Term Code is required.',
            ], 422);
        }

        try {
            $results = DB::select(
                'EXEC sproc_PHP_PayTermRef @mode = ?, @params = ?',
                ['Get', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('PayTerm get failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function upsert(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array'
            ]);

            $params = json_encode(['json_data' => $validated['json_data']]);

            $rows = DB::select(
                'EXEC sproc_PHP_PayTermRef @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            $first = !empty($rows) ? (array) $rows[0] : [];
            $errorCount = (int) ($first['errorcount'] ?? $first['errorCount'] ?? 0);
            $errorMsg = $first['errormsg'] ?? $first['errorMsg'] ?? '';

            if ($errorCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMsg ?: 'Failed to save pay term.',
                    'data' => $rows,
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pay term saved successfully.',
                'data' => $rows,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayTerm upsert failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save pay term: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array'
            ]);

            $params = json_encode(['json_data' => $validated['json_data']]);

            $rows = DB::select(
                'EXEC sproc_PHP_PayTermRef @mode = ?, @params = ?',
                ['Delete', $params]
            );

            $first = !empty($rows) ? (array) $rows[0] : [];
            $errorCount = (int) ($first['errorcount'] ?? $first['errorCount'] ?? 0);
            $errorMsg = $first['errormsg'] ?? $first['errorMsg'] ?? '';

            if ($errorCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMsg ?: 'Failed to delete pay term.',
                    'data' => $rows,
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pay term successfully deleted.',
                'data' => $rows,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->validator->errors()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayTerm delete failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete pay term: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkInUsed(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_PayTermRef @mode = ?, @params = ?',
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

    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_PayTermRef @mode = ?, @params = ?',
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