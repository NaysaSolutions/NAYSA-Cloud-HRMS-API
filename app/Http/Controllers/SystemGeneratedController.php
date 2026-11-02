<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SystemGeneratedController extends Controller
{
    private const PROCEDURE = 'sproc_PHP_Tran_SystemGenerated';

    public function index(Request $request)
    {
        return $this->runWithFilters($request, 'List');
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
            'jsonData.clientCode' => 'nullable|string|max:70',
            'jsonData.deptCode' => 'nullable|string|max:15',
            'jsonData.statCode' => 'nullable|string|max:15',
            'jsonData.employeeStatus' => 'nullable|string|max:15',
            'jsonData.posCode' => 'nullable|string|max:100',
            'jsonData.positionCode' => 'nullable|string|max:100',
            'jsonData.empNo' => 'nullable|string|max:15',
            'jsonData.employeeNo' => 'nullable|string|max:15',
            'jsonData.payFrequency' => 'required|string|in:S,M,W',
            'jsonData.cutoffCode' => 'required|string|max:8',
            'jsonData.search' => 'nullable|string|max:200',
        ]);

        return $this->runProcedure('SelectionEmployees', $data['jsonData']);
    }

    public function get(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.sgId' => 'nullable|string|max:50|required_without:jsonData.sgNo',
            'jsonData.sgNo' => 'nullable|string|max:10|required_without:jsonData.sgId',
        ]);

        return $this->runProcedure('Get', $data['jsonData']);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.sgId' => 'nullable|string|max:50',
            'jsonData.sgNo' => 'nullable|string|max:10',
            'jsonData.sgDate' => 'required|date',
            'jsonData.cutoffType' => 'required|string|in:S,M,W',
            'jsonData.cutoffFrequency' => 'required|string|in:S1,S2,M,W1,W2,W3,W4',
            'jsonData.cutoffCode' => 'required|string|max:8',
            'jsonData.cutoffStartDate' => 'required|date',
            'jsonData.cutoffEndDate' => 'required|date|after_or_equal:jsonData.cutoffStartDate',
            'jsonData.tranType' => 'nullable|string|max:50',
            'jsonData.edType' => 'nullable|string|in:EARN,DED',
            'jsonData.remarks' => 'nullable|string|max:4000',
            'jsonData.genCode' => 'nullable|string|max:1',
            'jsonData.userCode' => 'required|string|max:100',
            'jsonData.rows' => 'required|array|min:1',
            'jsonData.rows.*.groupId' => 'nullable|string|max:200',
            'jsonData.rows.*.lineNo' => 'nullable|integer|min:1',
            'jsonData.rows.*.empNo' => 'required|string|max:15',
            'jsonData.rows.*.edCode' => 'required|string|max:15',
            'jsonData.rows.*.startDate' => 'required|date',
            'jsonData.rows.*.endDate' => 'required|date|after_or_equal:jsonData.rows.*.startDate',
            'jsonData.rows.*.amount' => 'required|numeric|min:0|max:9999999999999999.99',
            'jsonData.rows.*.frequency' => 'nullable|string|in:S,M,W|required_without:jsonData.rows.*.edFreq',
            'jsonData.rows.*.edFreq' => 'nullable|string|in:S,M,W|required_without:jsonData.rows.*.frequency',
            'jsonData.rows.*.holdFlag' => 'nullable|string|in:Y,N',
            'jsonData.rows.*.genCode' => 'nullable|string|max:5',
        ]);

        $input = $data['jsonData'];
        $allowed = ['S' => ['S1', 'S2'], 'M' => ['M'], 'W' => ['W1', 'W2', 'W3', 'W4']];
        if (!in_array($input['cutoffFrequency'], $allowed[$input['cutoffType']], true)) {
            throw ValidationException::withMessages([
                'jsonData.cutoffFrequency' => 'Cut-Off Frequency does not match Cut-Off Type.',
            ]);
        }

        return $this->runProcedure('Save', $input);
    }

    public function post(Request $request)
    {
        return $this->runDocumentAction($request, 'Post');
    }

    public function cancel(Request $request)
    {
        return $this->runDocumentAction($request, 'Cancel');
    }

    public function history(Request $request)
    {
        // AllTranHistory uses json_data; transaction forms use jsonData.
        if (!$request->has('jsonData')) {
            $input = $request->input('json_data', []);
            if (is_string($input)) {
                try {
                    $input = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    throw ValidationException::withMessages(['json_data' => 'A valid JSON object is required.']);
                }
            }
            if (!is_array($input)) {
                throw ValidationException::withMessages(['json_data' => 'History filters must be an object.']);
            }
            $request->merge(['jsonData' => $input['jsonData'] ?? $input['json_data'] ?? $input]);
        }

        return $this->runWithFilters($request, 'History');
    }

    public function loadED(Request $request)
    {
        return $this->runWithFilters($request, 'LoadED', [
            'jsonData.edCode' => 'nullable|string|max:15',
            'jsonData.includeInactive' => 'nullable|boolean',
        ]);
    }

    public function loadEDGL(Request $request)
    {
        return $this->runWithFilters($request, 'LoadEDGL', [
            'jsonData.edCode' => 'nullable|string|max:15',
        ]);
    }

    public function preparePayrun(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.empNo' => 'nullable|string|max:15|required_without:jsonData.employeeNo',
            'jsonData.employeeNo' => 'nullable|string|max:15|required_without:jsonData.empNo',
            'jsonData.cutoffCode' => 'required|string|max:8',
            'jsonData.cutoffFrequency' => 'required|string|max:50',
            'jsonData.payrunType' => 'nullable|string|max:20',
            'jsonData.includeNotForPayroll' => 'nullable|boolean',
        ]);

        return $this->runProcedure('PreparePayrunSG', $data['jsonData']);
    }

    private function runDocumentAction(Request $request, string $mode)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.sgId' => 'required|string|max:50',
            'jsonData.userCode' => 'required|string|max:100',
            'jsonData.remarks' => $mode === 'Cancel' ? 'required|string|max:4000' : 'nullable|string|max:4000',
        ]);

        return $this->runProcedure($mode, $data['jsonData']);
    }

    private function runWithFilters(Request $request, string $mode, array $extraRules = [])
    {
        $data = $request->validate(array_merge([
            'jsonData' => 'sometimes|array',
            'jsonData.sgId' => 'nullable|string|max:50',
            'jsonData.sgNo' => 'nullable|string|max:10',
            'jsonData.cutoffCode' => 'nullable|string|max:8',
            'jsonData.cutoffFrequency' => 'nullable|string|max:50',
            'jsonData.edType' => 'nullable|string|in:EARN,DED',
            'jsonData.branchCode' => 'nullable|string|max:15',
            'jsonData.startDate' => 'nullable|date',
            'jsonData.endDate' => 'nullable|date|after_or_equal:jsonData.startDate',
        ], $extraRules));

        return $this->runProcedure($mode, $data['jsonData'] ?? []);
    }

    private function runProcedure(string $mode, ?array $jsonData = null)
    {
        try {
            $results = $jsonData === null
                ? DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?', [$mode])
                : DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?, @params = ?', [
                    $mode,
                    json_encode(['jsonData' => $jsonData], JSON_THROW_ON_ERROR),
                ]);

            foreach ($results as $result) {
                if ((int) ($result->errorCount ?? 0) > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => $result->errorMsg ?? 'System Generated transaction failed.',
                        'data' => $results,
                    ], 422);
                }
            }

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Throwable $exception) {
            Log::error('System Generated transaction procedure failed.', [
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }
}
