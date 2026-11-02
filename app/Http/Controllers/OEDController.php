<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OEDController extends Controller
{
    private const PROCEDURE = 'sproc_PHP_Tran_OtherEarningsDeductions';

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
            'jsonData.groupCode' => 'nullable|string|max:30',
            'jsonData.branchCode' => 'nullable|string|max:30',
            'jsonData.deptCode' => 'nullable|string|max:30',
            'jsonData.clientCode' => 'nullable|string|max:30',
            'jsonData.employeeStatus' => 'nullable|string|max:30',
            'jsonData.statCode' => 'nullable|string|max:30',
            'jsonData.positionCode' => 'nullable|string|max:30',
            'jsonData.posCode' => 'nullable|string|max:30',
            'jsonData.employeeNo' => 'nullable|string|max:30',
            'jsonData.empNo' => 'nullable|string|max:30',
            'jsonData.search' => 'nullable|string|max:200',
            'jsonData.payFrequency' => 'required|string|in:S,M,W',
            'jsonData.cutoffCode' => 'required|string|max:30',
        ]);

        return $this->runProcedure(
            'SelectionEmployees',
            $this->encodeJsonData($data['jsonData']),
        );
    }

    public function get(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.oedId' => 'nullable|string|max:50|required_without:jsonData.oedNo',
            'jsonData.oedNo' => 'nullable|string|max:20|required_without:jsonData.oedId',
        ]);

        return $this->runProcedure('Get', $this->encodeJsonData($data['jsonData']));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.oedId' => 'nullable|string|max:50',
            'jsonData.oedNo' => 'nullable|string|max:20',
            'jsonData.oedDate' => 'required|date',
            'jsonData.cutoffType' => 'required|string|in:S,M,W',
            'jsonData.cutoffFrequency' => 'required|string|in:S1,S2,M,W1,W2,W3,W4',
            'jsonData.cutoffCode' => 'required|string|max:30',
            'jsonData.cutoffStartDate' => 'required|date',
            'jsonData.cutoffEndDate' => 'required|date|after_or_equal:jsonData.cutoffStartDate',
            'jsonData.tranType' => 'nullable|string|max:50',
            'jsonData.edType' => 'required|string|in:EARN,DED',
            'jsonData.branchCode' => 'nullable|string|max:30',
            'jsonData.remarks' => 'nullable|string|max:4000',
            'jsonData.refOt' => 'nullable|string|max:100',
            'jsonData.refLv' => 'nullable|string|max:100',
            'jsonData.refTs' => 'nullable|string|max:100',
            'jsonData.genCode' => 'nullable|string|max:20',
            'jsonData.userCode' => 'required|string|max:100',
            'jsonData.rows' => 'required|array|min:1',
            'jsonData.rows.*.groupId' => 'nullable|string|max:200',
            'jsonData.rows.*.lineNo' => 'nullable|integer|min:1',
            'jsonData.rows.*.empNo' => 'required|string|max:30',
            'jsonData.rows.*.edCode' => 'required|string|max:30',
            'jsonData.rows.*.edType' => 'nullable|string|in:EARN,DED',
            'jsonData.rows.*.refCode' => 'nullable|string|max:50',
            'jsonData.rows.*.date' => 'nullable|date',
            'jsonData.rows.*.startDate' => 'required|date',
            'jsonData.rows.*.endDate' => 'required|date',
            'jsonData.rows.*.oedDays' => 'required|numeric|min:0',
            'jsonData.rows.*.oedHrs' => 'required|numeric|min:0',
            'jsonData.rows.*.oedMins' => 'required|integer|between:0,59',
            'jsonData.rows.*.oedRate' => 'required|numeric|min:0',
            'jsonData.rows.*.amount' => 'required|numeric|min:0',
            'jsonData.rows.*.dailyRate' => 'nullable|numeric|min:0',
            'jsonData.rows.*.hourRate' => 'nullable|numeric|min:0',
            'jsonData.rows.*.allowRate' => 'nullable|numeric|min:0',
            'jsonData.rows.*.projAllow' => 'nullable|numeric|min:0',
            'jsonData.rows.*.regDaysMonth' => 'nullable|numeric|min:0',
            'jsonData.rows.*.refNo' => 'nullable|string|max:100',
            'jsonData.rows.*.refDate' => 'nullable|date',
            'jsonData.rows.*.refOt' => 'nullable|string|max:100',
            'jsonData.rows.*.refLv' => 'nullable|string|max:100',
            'jsonData.rows.*.refTs' => 'nullable|string|max:100',
            'jsonData.rows.*.genCode' => 'nullable|string|max:20',
            'jsonData.rows.*.remarks' => 'nullable|string|max:4000',
            'jsonData.rows.*.tranType' => 'nullable|string|max:50',
            'jsonData.rows.*.payregFlag' => 'nullable|string|in:Y,N',
        ]);

        $input = $data['jsonData'];
        $allowed = [
            'S' => ['S1', 'S2'],
            'M' => ['M'],
            'W' => ['W1', 'W2', 'W3', 'W4'],
        ];

        if (!in_array($input['cutoffFrequency'], $allowed[$input['cutoffType']], true)) {
            throw ValidationException::withMessages([
                'jsonData.cutoffFrequency' => 'Cut-Off Frequency does not match Pay Frequency.',
            ]);
        }

        foreach ($input['rows'] as $index => $row) {
            if (strtotime($row['endDate']) < strtotime($row['startDate'])) {
                throw ValidationException::withMessages([
                    "jsonData.rows.$index.endDate" => 'End Date cannot be earlier than Start Date.',
                ]);
            }

            if (isset($row['edType']) && $row['edType'] !== $input['edType']) {
                throw ValidationException::withMessages([
                    "jsonData.rows.$index.edType" => 'ED Code category must match the transaction category.',
                ]);
            }

            if ((float) $row['oedDays'] > 0
                && ((float) $row['oedHrs'] > 0 || (int) $row['oedMins'] > 0)) {
                throw ValidationException::withMessages([
                    "jsonData.rows.$index.oedDays" => 'Enter Days or Hours / Minutes, not both.',
                ]);
            }
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
            'jsonData.oedId' => 'required|string|max:50',
            'jsonData.userCode' => 'required|string|max:100',
            'jsonData.remarks' => 'required|string|max:4000',
        ]);

        return $this->runProcedure('Cancel', $this->encodeJsonData($data['jsonData']));
    }

    public function history(Request $request)
    {
        // SearchGlobalTranHistory sends json_data; other transaction requests use jsonData.
        $jsonData = $request->input('json_data', $request->input('jsonData', []));

        if (is_string($jsonData)) {
            try {
                $decoded = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw ValidationException::withMessages([
                    'json_data' => 'Transaction history requires valid JSON.',
                ]);
            }

            $jsonData = $decoded['json_data'] ?? $decoded['jsonData'] ?? $decoded;
        }

        if (!is_array($jsonData)) {
            throw ValidationException::withMessages([
                'json_data' => 'Transaction history filters must be an object.',
            ]);
        }

        return $this->runProcedure('History', $this->encodeJsonData($jsonData));
    }

    public function loadED(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'sometimes|array',
            'jsonData.edCode' => 'nullable|string|max:30',
            'jsonData.edType' => 'nullable|string|in:EARN,DED',
            'jsonData.includeInactive' => 'nullable|boolean',
        ]);

        return $this->runProcedure('LoadED', $this->encodeJsonData($data['jsonData'] ?? []));
    }

    public function loadEDGL(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'sometimes|array',
            'jsonData.edCode' => 'nullable|string|max:30',
            'jsonData.edType' => 'nullable|string|in:EARN,DED',
        ]);

        return $this->runProcedure('LoadEDGL', $this->encodeJsonData($data['jsonData'] ?? []));
    }

    public function preparePayrunOED(Request $request)
    {
        // Read-only preparation: this stored procedure mode does not post payroll.
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.employeeNo' => 'required|string|max:30',
            'jsonData.cutoffCode' => 'required|string|max:30',
            'jsonData.cutoffFrequency' => 'required|string|in:S1,S2,M,W1,W2,W3,W4',
            'jsonData.payrunType' => 'nullable|string|in:REG,13TH',
            'jsonData.includeNotForPayroll' => 'nullable|boolean',
        ]);

        return $this->runProcedure('PreparePayrunOED', $this->encodeJsonData($data['jsonData']));
    }

    private function runDocumentAction(Request $request, string $mode)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.oedId' => 'required|string|max:50',
            'jsonData.userCode' => 'required|string|max:100',
        ]);

        return $this->runProcedure($mode, $this->encodeJsonData($data['jsonData']));
    }

    private function runWithJsonData(Request $request, string $mode, bool $required = true)
    {
        $data = $request->validate([
            'jsonData' => $required ? 'required|array' : 'sometimes|array',
        ]);

        return $this->runProcedure(
            $mode,
            $this->encodeJsonData($data['jsonData'] ?? []),
        );
    }

    private function runProcedure(string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select('exec dbo.' . self::PROCEDURE . ' @mode = ?', [$mode])
                : DB::select('exec dbo.' . self::PROCEDURE . ' @mode = ?, @params = ?', [$mode, $params]);

            $result = $results[0] ?? null;
            if ($result && (int) ($result->errorCount ?? $result->errorcount ?? 0) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $result->errorMsg ?? $result->errormsg ?? 'OED operation failed.',
                    'data' => $results,
                ], 422);
            }

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Throwable $exception) {
            Log::error('Other Earnings/Deductions transaction procedure failed.', [
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process Other Earnings/Deductions transaction.',
            ], 500);
        }
    }

    private function encodeJsonData(array $jsonData): string
    {
        return json_encode(['jsonData' => $jsonData], JSON_THROW_ON_ERROR);
    }
}
