<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckTemplateController extends Controller
{
    public function index(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?',
                ['Load']
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Loading check templates failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function lookup(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?',
                ['Lookup']
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Check template lookup failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function get(Request $request)
    {
        $request->validate([
            'TEMPLATE_ID' => 'required|string',
        ]);

        $params = $request->input('TEMPLATE_ID');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['Get', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get check template failed:', [
                'error' => $e->getMessage(),
                'templateId' => $params,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function getByCode(Request $request)
    {
        $request->validate([
            'TEMPLATE_CODE' => 'required|string',
        ]);

        $params = $request->input('TEMPLATE_CODE');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['GetByCode', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get check template by code failed:', [
                'error' => $e->getMessage(),
                'templateCode' => $params,
            ]);

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

            $params = $request->get('json_data');

            $results = DB::select(
                'EXEC sproc_PHP_CKT @params = :json_data, @mode = :mode',
                [
                    'json_data' => $params,
                    'mode' => 'Upsert',
                ]
            );

            return response()->json([
                'status' => 'success',
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Saving check template failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save check template: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function delete(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array',
            ]);

            $params = json_encode([
                'json_data' => $validated['json_data']
            ]);

            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['Delete', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Deleting check template failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function setInactive(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array',
            ]);

            $params = json_encode([
                'json_data' => $validated['json_data']
            ]);

            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['SetInactive', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Set check template inactive failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function loadBankMapping(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?',
                ['LoadBankMapping']
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Loading bank check template mapping failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function upsertBankMapping(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array',
            ]);

            $params = json_encode([
                'json_data' => $validated['json_data']
            ]);

            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['UpsertBankMapping', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Saving bank check template mapping failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function removeBankMapping(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array',
            ]);

            $params = json_encode([
                'json_data' => $validated['json_data']
            ]);

            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['RemoveBankMapping', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Removing bank check template mapping failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function getBankTemplate(Request $request)
    {
        $request->validate([
            'BANK_CODE' => 'required|string',
        ]);

        $params = $request->input('BANK_CODE');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['GetBankTemplate', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get check template by bank failed:', [
                'error' => $e->getMessage(),
                'bankCode' => $params,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function checkInUsed(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array',
            ]);

            $params = json_encode([
                'json_data' => $validated['json_data']
            ]);

            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['CheckInUsed', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Check template in-used validation failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function checkDuplicate(Request $request)
    {
        try {
            $validated = $request->validate([
                'json_data' => 'required|array',
            ]);

            $params = json_encode([
                'json_data' => $validated['json_data']
            ]);

            $results = DB::select(
                'EXEC sproc_PHP_CKT @mode = ?, @params = ?',
                ['CheckDuplicate', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Check template duplicate validation failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}