<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ARDSController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|json',
            ]);

            $params = $request->get('json_data');

            $results = DB::select(
                'EXEC sproc_PHP_ARDS @mode = ?, @params = ?',
                ['get', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('ARDS Index Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error executing ARDS index.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function get(Request $request)
    {
        $jsonData = $request->all();
        $jsonString = json_encode($jsonData);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_ARDS @mode = ?, @params = ?',
                ['Get', $jsonString]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('ARDS Get Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error executing ARDS get.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function posting(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_ARDS @mode = ?',
                ['Posting']
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('ARDS Posting Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error executing ARDS posting.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Upsert';

            $result = DB::select(
                'EXEC sproc_PHP_ARDS @mode = ?, @params = ?',
                [$mode, $params]
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('ARDS Upsert Error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error executing ARDS Upsert.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function finalize(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array'
            ]);

            $params = json_encode(['json_data' => $validated['json_data']]);

            $results = DB::select(
                'EXEC sproc_PHP_ARDS @mode = ?, @params = ?',
                ['Finalize', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('ARDS Finalize Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error executing ARDS Finalize.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function cancel(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Cancel';

            $result = DB::select(
                'EXEC sproc_PHP_ARDS @mode = ?, @params = ?',
                [$mode, $params]
            );

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('ARDS Cancel Error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error executing ARDS Cancel.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function history(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'History';

            $results = DB::select(
                'EXEC sproc_PHP_ARDS @mode = ?, @params = ?',
                [$mode, $params]
            );

            return response()->json([
                'status' => 'success',
                'data' => $results,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('ARDS History Error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error executing ARDS History.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function getCRDS_OpenDetail(Request $request)
    {
        try {
            /*
                Supports React fetchDataJson payload:
                {
                    "branchCode": "HO",
                    "bankCode": "BPI"
                }

                Also supports PARAMS payload:
                {
                    "PARAMS": "{\"json_data\":{\"branchCode\":\"HO\",\"bankCode\":\"BPI\"}}"
                }
            */

            if ($request->has('PARAMS')) {
                $params = $request->input('PARAMS');

                $decoded = json_decode($params, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid PARAMS JSON.',
                        'details' => json_last_error_msg(),
                    ], 422);
                }

                if (!isset($decoded['json_data'])) {
                    $params = json_encode(['json_data' => $decoded]);
                }

            } else {
                $payload = $request->all();
                $params = json_encode(['json_data' => $payload]);
            }

            $results = DB::select(
                'EXEC sproc_PHP_ARDS @mode = ?, @params = ?',
                ['GetCRDS_OpenDetail', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('ARDS GetCRDS_OpenDetail Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error executing ARDS GetCRDS_OpenDetail.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}