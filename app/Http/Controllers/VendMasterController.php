<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;

class VendMasterController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = max((int) $request->query('page', 1), 1);
            $pageSize = (int) $request->query('pageSize', 300);
            $pageSize = max(min($pageSize, 1000), 1);

            $jsonData = [
                'page' => $page,
                'pageSize' => $pageSize,
                'filter' => trim((string) $request->query('filter', '')),
                'sltypeCode' => trim((string) $request->query('sltypeCode', '')),
                'vendCode' => trim((string) $request->query('vendCode', '')),
                'vendName' => trim((string) $request->query('vendName', '')),
                'businessName' => trim((string) $request->query('businessName', '')),
                'vendTin' => trim((string) $request->query('vendTin', '')),
                'branchCode' => trim((string) $request->query('branchCode', '')),
                'active' => trim((string) $request->query('active', '')),
            ];

            $params = json_encode(['json_data' => $jsonData]);

            $results = DB::select(
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
                ['Load', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Vendor list load failed:', ['error' => $e->getMessage()]);

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
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
                ['Lookup', $jsonString]
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
            'VEND_CODE' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
                ['Get', $request->input('VEND_CODE')]
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
                'json_data' => 'required|json',
            ]);

            $params = $request->input('json_data');

            $results = DB::select(
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Vendor save failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save vendor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $request->validate([
                'VEND_CODE' => 'required|string',
            ]);

            $vendCode = $request->input('VEND_CODE');
            $userCode = $request->input('USER_CODE', '');

            $params = json_encode([
                'json_data' => [
                    'vendCode' => $vendCode,
                    'userCode' => $userCode,
                ]
            ]);

            $results = DB::select(
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
                ['Delete', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Vendor delete failed:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vendor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
                ['CheckDuplicate', $request->input('json_data')]
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

    public function checkDuplicateName(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
                ['CheckDuplicateName', $request->input('json_data')]
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

    public function checkInUsed(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
                ['CheckInUsed', $request->input('json_data')]
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

    public function addDetail(Request $request)
    {
        try {
            $jsonString = json_encode($request->all());

            $results = DB::select(
                'EXEC sproc_PHP_VendMast @mode = ?, @params = ?',
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
}