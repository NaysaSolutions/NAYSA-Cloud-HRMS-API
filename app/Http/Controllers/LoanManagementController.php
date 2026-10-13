<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanManagementController extends Controller
{
    private const PROCEDURE = 'sproc_PHP_Tran_Loan';

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
            'jsonData.groupCode' => 'required|string|max:15',
            'jsonData.branchCode' => 'nullable|string|max:15',
            'jsonData.deptCode' => 'nullable|string|max:15',
            'jsonData.employeeNo' => 'nullable|string|max:15',
            'jsonData.payFrequency' => 'required|string|in:S,M,D',
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
            'jsonData.loanId' => 'nullable|string|max:50|required_without:jsonData.loanNo',
            'jsonData.loanNo' => 'nullable|string|max:10|required_without:jsonData.loanId',
        ]);

        return $this->runProcedure('Get', $this->encodeJsonData($data['jsonData']));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.loanId' => 'nullable|string|max:50',
            'jsonData.transactionDate' => 'required|date',
            'jsonData.cutoffFrequency' => 'required|string|in:S,M,D',
            'jsonData.remarks' => 'nullable|string|max:4000',
            'jsonData.userCode' => 'required|string|max:100',
            'jsonData.rows' => 'required|array|min:1',
            'jsonData.rows.*.groupId' => 'nullable|uuid',
            'jsonData.rows.*.empNo' => 'required|string|max:15',
            'jsonData.rows.*.referenceNo' => 'required|string|max:200',
            'jsonData.rows.*.voucherNo' => 'nullable|string|max:100',
            'jsonData.rows.*.dateGranted' => 'required|date',
            'jsonData.rows.*.beginningCutoff' => 'nullable|string|max:8',
            'jsonData.rows.*.endingCutoff' => 'nullable|string|max:8',
            'jsonData.rows.*.deductionStartDate' => 'required|date',
            'jsonData.rows.*.deductionEndDate' => 'nullable|date|after_or_equal:jsonData.rows.*.deductionStartDate',
            'jsonData.rows.*.frequency' => 'required|string|max:5',
            'jsonData.rows.*.loanCode' => 'required|string|max:15',
            'jsonData.rows.*.loanAmount' => 'required|numeric|gt:0',
            'jsonData.rows.*.interestAmount' => 'nullable|numeric|min:0',
            'jsonData.rows.*.amortizationAmount' => 'required|numeric|gt:0',
            'jsonData.rows.*.holdFlag' => 'nullable|in:Y,N',
        ]);

        return $this->runProcedure('Save', $this->encodeJsonData($data['jsonData']));
    }

    public function post(Request $request)
    {
        return $this->runDocumentAction($request, 'Post');
    }

    public function cancel(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.loanId' => 'required|string|max:50',
            'jsonData.userCode' => 'required|string|max:100',
            'jsonData.remarks' => 'required|string|max:4000',
        ]);

        return $this->runProcedure('Cancel', $this->encodeJsonData($data['jsonData']));
    }

    public function paymentHistory(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.loanId' => 'required|string|max:50',
            'jsonData.groupId' => 'nullable|uuid',
        ]);

        return $this->runProcedure(
            'PaymentHistory',
            $this->encodeJsonData($data['jsonData']),
        );
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
            'jsonData.loanId' => 'required|string|max:50',
            'jsonData.userCode' => 'required|string|max:100',
        ]);

        return $this->runProcedure($mode, $this->encodeJsonData($data['jsonData']));
    }

    private function runWithJsonData(Request $request, string $mode, bool $required = true)
    {
        $rules = ['jsonData' => $required ? 'required|array' : 'sometimes|array'];
        $data = $request->validate($rules);

        return $this->runProcedure(
            $mode,
            $this->encodeJsonData($data['jsonData'] ?? []),
        );
    }

    private function runProcedure(string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?', [$mode])
                : DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?, @params = ?', [$mode, $params]);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Throwable $exception) {
            Log::error('Loan transaction procedure failed.', [
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
    }
}
