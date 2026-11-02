<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollOvertimeLedgerController extends Controller
{
    private const PROCEDURE = 'dbo.sproc_PHP_Payroll_OvertimeLedger';

    public function index(Request $request)
    {
        $validated = $request->validate([
            'jsonData'                    => 'required|array',
            'jsonData.payFrequency'       => 'required|string|in:S,M,W',
            'jsonData.beginningCutoff'    => 'required|string|max:30',
            'jsonData.endingCutoff'       => 'required|string|max:30',
            'jsonData.branchCode'         => 'nullable|string|max:15',
            'jsonData.groupCode'          => 'nullable|string|max:15',
            'jsonData.deptCode'           => 'nullable|string|max:15',
            'jsonData.employeeNo'         => 'nullable|string|max:30',
            'jsonData.employeeName'       => 'nullable|string|max:200',
            'jsonData.otCode'             => 'nullable|string|max:20',
            'jsonData.documentStatus'     => 'nullable|string|in:F,D,X',
            'jsonData.userCode'           => 'nullable|string|max:100',
        ]);

        try {
            $params = json_encode(
                ['jsonData' => $validated['jsonData']],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );

            // A separate, read-only procedure avoids altering the working
            // Overtime Transaction stored procedure and its existing modes.
            $result = DB::select(
                'exec ' . self::PROCEDURE . ' @mode = ?, @params = ?',
                ['Ledger', $params]
            );

            $first = $result[0] ?? null;
            if ($first && (int) ($first->errorCount ?? 0) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $first->errorMsg ?? 'Unable to generate overtime ledger.',
                    'data' => $result,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Payroll Overtime Ledger failed.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve the overtime ledger.',
            ], 500);
        }
    }
}
