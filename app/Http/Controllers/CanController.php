<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CanController extends Controller
{
    private function execCanSproc(string $mode, array $jsonData = [])
    {
        try {
            $params = json_encode([
                'json_data' => $jsonData
            ]);

            $rows = DB::select(
                "exec dbo.sproc_PHP_CAN @mode = ?, @params = ?",
                [$mode, $params]
            );

            if (empty($rows)) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No result returned.'
                ], 200);
            }

            $firstRow = (array) $rows[0];

            if (array_key_exists('errorCount', $firstRow) && (int) $firstRow['errorCount'] > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $firstRow['errorMsg'] ?? 'Validation error.',
                    'errors' => $firstRow
                ], 422);
            }

            if (array_key_exists('result', $firstRow)) {
                $result = $firstRow['result'];

                if ($this->isJson($result)) {
                    return response()->json([
                        'success' => true,
                        'data' => json_decode($result, true)
                    ], 200);
                }

                return response()->json([
                    'success' => true,
                    'data' => $result
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $firstRow
            ], 200);

        } catch (Throwable $e) {
            Log::error('CAN Controller Error', [
                'mode' => $mode,
                'json_data' => $jsonData,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function isJson($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function getPayload(Request $request): array
    {
        return $request->input('json_data', $request->all());
    }

    public function getHistory(Request $request)
    {
        /*
            Important:
            This follows the working PRController history pattern.

            AllTranHistory expects the database result row to be returned as-is:
            [
              { result: '[{"CAN_Summary": [...], "CAN_Detail": [...]}]' }
            ]

            Do NOT use execCanSproc() here because execCanSproc() JSON-decodes
            the result column. That is fine for GetCAN / lookups, but not for
            SearchGlobalTranHistory.jsx / AllTranHistory.
        */

        $payload = $this->getPayload($request);

        try {
            $params = json_encode([
                'json_data' => $payload
            ]);

            $rows = DB::select(
                'EXEC dbo.sproc_PHP_CAN @mode = ?, @params = ?',
                ['History', $params]
            );

            return response()->json([
                'status' => 'success',
                'success' => true,
                'data' => $rows
            ], 200);

        } catch (Throwable $e) {
            Log::error('CAN History Controller Error', [
                'mode' => 'History',
                'json_data' => $payload,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Error executing CAN History.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function getOpenPR(Request $request)
    {
        return $this->execCanSproc('GetOpenPR', $this->getPayload($request));
    }

    public function getOpenPRDetail(Request $request)
    {
        $payload = $this->getPayload($request);

        $validator = Validator::make($payload, [
            'prIds' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one PR.',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->execCanSproc('GetOpenPRDetail', $payload);
    }

    public function getCAN(Request $request)
    {
        return $this->execCanSproc('GetCAN', $this->getPayload($request));
    }

    public function upsert(Request $request)
    {
        $payload = $this->getPayload($request);

        $validator = Validator::make($payload, [
            'branchCode' => 'required|string',
            'canDate' => 'required',
            'userCode' => 'required|string',
            'detailRows' => 'required|array|min:1',
            'supplierRows' => 'nullable|array',
            'prRows' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete the required fields.',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->execCanSproc('Upsert', $payload);
    }

  
    
    
public function cancel(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Cancel';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_CAN  @mode = ?, @params = ?', [
                $mode,
                $params
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing CAN Cancel.',
                'details' => $e->getMessage()
            ], 500);
        }
}


    public function submit(Request $request)
    {
        $payload = $this->getPayload($request);

        $validator = Validator::make($payload, [
            'canId' => 'required|string',
            'userCode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Canvass ID and User Code are required.',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->execCanSproc('Submit', $payload);
    }

    public function approve(Request $request)
    {
        $payload = $this->getPayload($request);

        $validator = Validator::make($payload, [
            'canId' => 'required|string',
            'userCode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Canvass ID and User Code are required.',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->execCanSproc('Approve', $payload);
    }

    public function award(Request $request)
    {
        $payload = $this->getPayload($request);

        $validator = Validator::make($payload, [
            'canId' => 'required|string',
            'userCode' => 'required|string',
            'selectedSupplierCode' => 'required|string',
            'selectedSupplierName' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a supplier before awarding.',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->execCanSproc('Award', $payload);
    }

    public function markPOGenerated(Request $request)
    {
        $payload = $this->getPayload($request);

        $validator = Validator::make($payload, [
            'canId' => 'required|string',
            'canSupplierId' => 'required|string',
            'poId' => 'required|string',
            'poNo' => 'required|string',
            'userCode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Canvass ID, Supplier ID, PO ID, PO No., and User Code are required.',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->execCanSproc('MarkPOGenerated', $payload);
    }


    public function markPOLinesGenerated(Request $request)
    {
        $payload = $this->getPayload($request);

        $validator = Validator::make($payload, [
            'canId' => 'required|string',
            'canSupplierId' => 'required|string',
            'poId' => 'nullable|string',
            'poNo' => 'nullable|string',
            'userCode' => 'required|string',
            'lineRows' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Canvass ID, Supplier ID, User Code, and generated PO line rows are required.',
                'errors' => $validator->errors()
            ], 422);
        }

        if (empty($payload['poId']) && empty($payload['poNo'])) {
            return response()->json([
                'success' => false,
                'message' => 'PO ID or PO No. is required.'
            ], 422);
        }

        return $this->execCanSproc('MarkPOLinesGenerated', $payload);
    }

    public function find(Request $request)
    {
        return $this->execCanSproc('Find', $this->getPayload($request));
    }
}