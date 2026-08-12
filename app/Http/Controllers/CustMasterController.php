<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CustMasterController extends Controller
{

    public function index(Request $request)
    {

        try {
            $results = DB::select(
                'EXEC sproc_PHP_CustMast @mode = ?',
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
        try {

            $jsonString = $request->query('json_data');

            $results = DB::select(
                'EXEC sproc_PHP_CustMast @mode = ?, @params = ?',
                ['Lookup', $jsonString]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
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
            'CUST_CODE' => 'required|string',
        ]);

        $params = $request->input('CUST_CODE');


        try {
            $results = DB::select(
                'EXEC sproc_PHP_CustMast @mode = ?, @params = ?',
                ['get', $params]
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
            $request->validate([
                'CUST_CODE' => 'required|string',
            ]);

            $jsonString = json_encode([
                'json_data' => [
                    'custCode' => $request->input('CUST_CODE'),
                ]
            ]);

            $results = DB::select(
                'EXEC sproc_PHP_CustMast @mode = ?, @params = ?',
                ['Delete', $jsonString]
            );

            $row = $results[0] ?? null;
            $errorCount = (int)($row->errorcount ?? $row->errorCount ?? 0);
            $errorMsg = (string)($row->errormsg ?? $row->errorMsg ?? '');

            if ($errorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                    'data' => $results,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully.',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer: ' . $e->getMessage(),
            ], 500);
        }
    }






    public function upsert(Request $request)
    {
        try {
            $request->validate([
                'json_data' => 'required|json',
            ]);

            $params = $request->input('json_data');

            $results = DB::select(
                'EXEC sproc_PHP_CustMast @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            $row = $results[0] ?? null;
            $errorCount = 0;
            $errorMsg = '';

            if ($row) {
                if (isset($row->errorcount) || isset($row->errorCount)) {
                    $errorCount = (int) ($row->errorcount ?? $row->errorCount ?? 0);
                    $errorMsg = (string) ($row->errormsg ?? $row->errorMsg ?? '');
                } elseif (isset($row->result)) {
                    $decoded = json_decode($row->result, true);
                    $decodedRow = is_array($decoded) && isset($decoded[0]) ? $decoded[0] : $decoded;

                    if (is_array($decodedRow)) {
                        $errorCount = (int) ($decodedRow['errorcount'] ?? $decodedRow['errorCount'] ?? 0);
                        $errorMsg = (string) ($decodedRow['errormsg'] ?? $decodedRow['errorMsg'] ?? '');
                    }
                }
            }

            if ($errorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg ?: 'Please complete the required fields.',
                    'data' => $results,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction saved successfully.',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Transaction save failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save transaction: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function addDetail(Request $request)
    {

        $jsonData = $request->all();
        $jsonString = json_encode($jsonData);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_CustMast @mode = ?, @params = ?',
                ['Add_Detail', $jsonString]
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



    public function generateGL(Request $request)
    {
        try {
            // Expecting json_data to be passed from client
            $jsonData = $request->input('json_data');

            if (!$jsonData) {
                return response()->json(['error' => 'Missing json_data'], 400);
            }

            // Convert JSON to string format
            $jsonString = json_encode(['json_data' => $jsonData], JSON_UNESCAPED_UNICODE);

            // Execute the stored procedure
            $results = DB::select("EXEC sproc_PHP_SVI @mode = ?, @params = ?", [
                'GenerateEntries',
                $jsonString
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Error executing sproc_PHP_SVI: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate entries.',
                'details' => $e->getMessage()
            ], 500);
        }
    }



    public function checkDuplicate(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_CustMast @mode = ?, @params = ?',
                ['CheckDuplicate', $request->input('json_data')]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkInUsed(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_CustMast @mode = ?, @params = ?',
                ['CheckInUsed', $request->input('json_data')]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
