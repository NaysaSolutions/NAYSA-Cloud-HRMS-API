<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveManagementController extends Controller
{
    public function generateCreditReferences()
    {
        return $this->runProcedure('sproc_PHP_Generate_LeaveCredit', 'References');
    }

    public function generateCredits(Request $request)
    {
        $data = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.asOfDate' => 'required|date',
        ]);

        return $this->runProcedure(
            'sproc_PHP_Generate_LeaveCredit',
            'Generate',
            $this->encodeFilters($data['jsonData']),
        );
    }

    public function saveGeneratedCredits(Request $request)
    {
        return $this->saveRows(
            $request,
            'sproc_PHP_Generate_LeaveCredit',
            'Generated leave credit save failed.',
            'Failed to save transaction: ',
        );
    }

    public function ledgerReferences()
    {
        return $this->runProcedure('sproc_PHP_Inq_LeaveLedger', 'References');
    }

    public function loadLedger(Request $request)
    {
        return $this->runWithFilters($request, 'sproc_PHP_Inq_LeaveLedger', 'Load');
    }

    public function balanceReferences()
    {
        return $this->runProcedure('sproc_PHP_Inq_LeaveCreditBalance', 'References');
    }

    public function loadBalances(Request $request)
    {
        return $this->runWithFilters($request, 'sproc_PHP_Inq_LeaveCreditBalance', 'Load');
    }

    public function recalculateBalances(Request $request)
    {
        return $this->runWithFilters($request, 'sproc_PHP_Inq_LeaveCreditBalance', 'Recalculate');
    }

    public function saveBalances(Request $request)
    {
        return $this->saveRows(
            $request,
            'sproc_PHP_Inq_LeaveCreditBalance',
            'Leave credit balance save failed.',
            'Failed to save leave credit balance: ',
        );
    }

    private function runWithFilters(Request $request, string $procedure, string $mode)
    {
        $data = $request->validate(['jsonData' => 'required|array']);

        return $this->runProcedure($procedure, $mode, $this->encodeFilters($data['jsonData']));
    }

    private function saveRows(Request $request, string $procedure, string $logMessage, string $errorMessage)
    {
        $data = $request->validate(['jsonData' => 'required|json']);

        try {
            $results = DB::select(
                "EXEC {$procedure} @mode = ?, @params = ?",
                ['Save', $data['jsonData']],
            );

            return response()->json(['status' => 'success', 'data' => $results]);
        } catch (\Exception $exception) {
            Log::error($logMessage, ['error' => $exception->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => $errorMessage . $exception->getMessage(),
            ], 500);
        }
    }

    private function runProcedure(string $procedure, string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select("EXEC {$procedure} @mode = ?", [$mode])
                : DB::select("EXEC {$procedure} @mode = ?, @params = ?", [$mode, $params]);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    private function encodeFilters(array $filters)
    {
        return json_encode(['jsonData' => $filters], JSON_THROW_ON_ERROR);
    }
}
