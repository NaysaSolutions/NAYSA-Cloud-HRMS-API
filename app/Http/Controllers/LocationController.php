<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function upsert(Request $request)
    {
        try {
            $inputData = $request->input('json_data');
            // React might stringify the payload. Decode if it's a string.
            if (is_string($inputData)) {
                $inputData = json_decode($inputData, true);
            }

            // Wrap the data in the "json_data" root so the SPROC JSON_VALUE path works
            $params = json_encode(['json_data' => $inputData]);

            // MUST use DB::select (not DB::statement) to capture the errorMsg/errorCount returned by SPROC
            $rows = DB::select('EXEC sproc_PHP_Location @mode = ?, @params = ?', [
                'Upsert',
                $params
            ]);

            return response()->json([
                'success' => true,
                'data'    => $rows, // React extracts validation from this resultset
                'message' => 'Location upsert executed.',
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => [['errorCount' => 1, 'errorMsg' => $e->getMessage()]],
                'message' => 'Error saving location.',
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $inputData = $request->input('json_data');
            if (is_string($inputData)) {
                $inputData = json_decode($inputData, true);
            }

            $params = json_encode(['json_data' => $inputData]);

            $rows = DB::select('EXEC sproc_PHP_Location @mode = ?, @params = ?', [
                'Delete',
                $params
            ]);

            return response()->json([
                'success' => true,
                'data'    => $rows,
                'message' => 'Location deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => [['errorCount' => 1, 'errorMsg' => $e->getMessage()]],
                'message' => 'Error deleting location.',
            ], 500);
        }
    }

    public function load(Request $request)
    {
        try {
            $params = json_encode(['json_data' => ['whFilter' => '']]);

            $rows = DB::select('EXEC sproc_PHP_Location @mode = ?, @params = ?', [
                'Load',
                $params
            ]);

            // CRITICAL FIX: SQL Server FOR JSON splits large results into 2033-byte rows.
            // Concatenate all rows before parsing to prevent truncation/loading failure.
            $jsonResult = '';
            foreach ($rows as $row) {
                $jsonResult .= $row->result ?? '';
            }

            return response()->json([
                'success' => true,
                'data'    => json_decode($jsonResult, true) ?? [],
                'message' => 'Location loaded successfully.',
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => [],
                'message' => 'Error loading location: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function get(Request $request)
    {
        try {
            $locCode = $request->query('locCode', '');

            $rows = DB::select('EXEC sproc_PHP_Location @mode = ?, @params = ?', [
                'Get',
                $locCode  // SPROC automatically wraps this in '{"json_data":{"locCode": "..."}}'
            ]);

            $jsonResult = '';
            foreach ($rows as $row) {
                $jsonResult .= $row->result ?? '';
            }

            return response()->json([
                'success' => true,
                'data'    => json_decode($jsonResult, true) ?? [],
                'message' => 'Location fetched successfully.',
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => [],
                'message' => 'Error fetching location: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function lookup(Request $request)
    {
        try {
            $filter = $request->query('filter', 'ActiveAll');

            $rows = DB::select('EXEC sproc_PHP_Location @params = ?, @mode = ?', [
                $filter,  // sproc wraps this for Lookup
                'Lookup'
            ]);

            return response()->json([
                'success' => true,
                'data'    => $rows,
                'message' => 'Location lookup loaded successfully.',
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => ['status' => 'error', 'details' => $e->getMessage()],
                'message' => 'Error loading location lookup.',
            ], 500);
        }
    }

    public function byWarehouse(Request $request)
    {
        try {
            $whCode = $request->input('json_data.whCode', '');

            $params = json_encode([
                "json_data" => [
                    "whCode" => $whCode
                ]
            ]);

            $rows = DB::select('EXEC dbo.sproc_PHP_Location @mode = ?, @params = ?', [
                'ByWarehouse',
                $params
            ]);

            $jsonResult = '';
            foreach ($rows as $row) {
                $jsonResult .= $row->result ?? '';
            }

            return response()->json([
                'success' => true,
                'data'    => json_decode($jsonResult, true) ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => [],
                'message' => 'Error fetching by warehouse: ' . $e->getMessage()
            ], 500);
        }
    }

public function checkInUsed(Request $request)
    {
        try {
            $inputData = $request->input('json_data');
            if (is_string($inputData)) {
                $inputData = json_decode($inputData, true);
            }

            $params = json_encode(['json_data' => $inputData]);

            $rows = DB::select('EXEC sproc_PHP_Location @mode = ?, @params = ?', [
                'checkInUsed', // Matches the SPROC mode
                $params
            ]);

            $jsonResult = '';
            foreach ($rows as $row) {
                $jsonResult .= $row->result ?? '';
            }

            return response()->json([
                'success' => true,
                'data'    => json_decode($jsonResult, true) ?? [],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => [],
                'message' => 'Error checking usage: ' . $e->getMessage(),
            ], 500);
        }
    }












}