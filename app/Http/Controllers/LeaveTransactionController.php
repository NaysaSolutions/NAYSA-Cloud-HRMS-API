<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveTransactionController extends Controller
{
    private const PROCEDURE = 'dbo.sproc_PHP_Tran_Leave';

    public function index(Request $request)
    {
        return $this->runWithJsonData($request, 'List', false);
    }

    public function selectionReferences()
    {
        return $this->runProcedure('SelectionReferences');
    }

    public function selectionEmployees(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.groupCode' => 'nullable|string|max:15',
            'jsonData.branchCode' => 'nullable|string|max:15',
            'jsonData.deptCode' => 'nullable|string|max:15',
            'jsonData.clientCode' => 'nullable|string|max:15',
            'jsonData.employeeStatus' => 'nullable|string|max:15',
            'jsonData.positionCode' => 'nullable|string|max:15',
            'jsonData.employeeNo' => 'nullable|string|max:15',
            'jsonData.payFrequency' => 'required|string|in:S,M,W',
        ]);

        return $this->runProcedure('SelectionEmployees', $this->encodeJsonData($data['jsonData']));
    }

    public function get(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.lvId' => 'nullable|string|max:50',
            'jsonData.lvNo' => 'nullable|string|max:10',
            'jsonData.leaveId' => 'nullable|string|max:50',
            'jsonData.leaveNo' => 'nullable|string|max:10',
        ]);

        $input = $this->normalizeDocumentIdentifiers($data['jsonData']);
        if (empty($input['lvId']) && empty($input['lvNo'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'jsonData.lvId' => 'lvId or lvNo is required.',
            ]);
        }

        return $this->runProcedure('Get', $this->encodeJsonData($input));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.lvId' => 'nullable|string|max:50',
            'jsonData.leaveId' => 'nullable|string|max:50',
            'jsonData.leaveDate' => 'required|date',
            'jsonData.cutoffType' => 'required|string|in:S,M,W',
            'jsonData.cutoffFrequency' => 'required|string|in:S1,S2,M,W1,W2,W3,W4',
            'jsonData.cutoffCode' => 'required|string|max:8',
            'jsonData.cutoffStartDate' => 'required|date',
            'jsonData.cutoffEndDate' => 'required|date|after_or_equal:jsonData.cutoffStartDate',
            'jsonData.remarks' => 'nullable|string|max:4000',
            'jsonData.userCode' => 'required|string|max:100',
            'jsonData.rows' => 'required|array|min:1',
            'jsonData.rows.*.groupId' => 'nullable|string|max:200',
            'jsonData.rows.*.lineNo' => 'nullable|integer|min:1',
            'jsonData.rows.*.empNo' => 'required|string|max:15',
            'jsonData.rows.*.lvCode' => 'required|string|max:10',
            'jsonData.rows.*.startDate' => 'required|date',
            'jsonData.rows.*.endDate' => 'required|date|after_or_equal:jsonData.rows.*.startDate',
            'jsonData.rows.*.lvHours' => 'required|numeric|gt:0',
            'jsonData.rows.*.lvDays' => 'required|numeric|gt:0',
            'jsonData.rows.*.hourRate' => 'required|numeric|min:0',
            'jsonData.rows.*.dailyRate' => 'nullable|numeric|min:0',
        ]);

        $allowed = ['S' => ['S1', 'S2'], 'M' => ['M'], 'W' => ['W1', 'W2', 'W3', 'W4']];
        $input = $this->normalizeDocumentIdentifiers($data['jsonData']);
        if (!in_array($input['cutoffFrequency'], $allowed[$input['cutoffType']], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'jsonData.cutoffFrequency' => 'Cut-Off Frequency does not match Cut-Off Type.',
            ]);
        }

        return $this->runProcedure('Save', $this->encodeJsonData($input));
    }

    public function post(Request $request)
    {
        return $this->runDocumentAction($request, 'Post');
    }

    public function cancel(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.lvId' => 'nullable|string|max:50',
            'jsonData.leaveId' => 'nullable|string|max:50',
            'jsonData.userCode' => 'required|string|max:100',
            'jsonData.remarks' => 'required|string|max:4000',
        ]);

        return $this->runDocumentActionPayload('Cancel', $data['jsonData']);
    }

    public function history(Request $request)
    {
        $jsonData = $request->input('json_data', []);
        if (is_string($jsonData)) {
            $decoded = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
            $jsonData = $decoded['json_data'] ?? $decoded['jsonData'] ?? $decoded;
        }

        return $this->runProcedure('History', $this->encodeJsonData($jsonData));
    }

    private function runDocumentAction(Request $request, string $mode)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.lvId' => 'nullable|string|max:50',
            'jsonData.leaveId' => 'nullable|string|max:50',
            'jsonData.userCode' => 'required|string|max:100',
        ]);

        return $this->runDocumentActionPayload($mode, $data['jsonData']);
    }

    private function normalizeDocumentIdentifiers(array $input): array
    {
        $input['lvId'] = ($input['lvId'] ?? null) ?: ($input['leaveId'] ?? null);
        $input['lvNo'] = ($input['lvNo'] ?? null) ?: ($input['leaveNo'] ?? null);
        if (in_array($input['lvId'], ['', '0'], true)) {
            $input['lvId'] = null;
        }
        unset($input['leaveId'], $input['leaveNo']);
        return $input;
    }

    private function runDocumentActionPayload(string $mode, array $input)
    {
        $input = $this->normalizeDocumentIdentifiers($input);
        if (empty($input['lvId'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'jsonData.lvId' => 'lvId is required.',
            ]);
        }
        return $this->runProcedure($mode, $this->encodeJsonData($input));
    }

    private function runWithJsonData(Request $request, string $mode, bool $required = true)
    {
        $rules = ['jsonData' => $required ? 'required|array' : 'sometimes|array'];
        $data = $request->validate($rules);

        return $this->runProcedure($mode, $this->encodeJsonData($data['jsonData'] ?? []));
    }

    private function runProcedure(string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?', [$mode])
                : DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?, @params = ?', [$mode, $params]);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Throwable $exception) {
            Log::error('Leave transaction procedure failed.', [
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    private function encodeJsonData(array $jsonData)
    {
        return json_encode(['jsonData' => $jsonData], JSON_THROW_ON_ERROR);
    }}
