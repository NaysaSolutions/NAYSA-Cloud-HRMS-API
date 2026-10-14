<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollYearEndController extends Controller
{
    public function bonusReferences()
    {
        return $this->runReferenceProcedure();
    }

    public function loadBonus(Request $request)
    {
        return $this->runWithFilters($request, 'sproc_PHP_Tran_Bonus');
    }

    public function taxReconReferences()
    {
        return $this->runReferenceProcedure();
    }

    public function loadTaxRecon(Request $request)
    {
        return $this->runWithFilters($request, 'sproc_PHP_Tran_TaxRecon');
    }

    public function alphalistReferences()
    {
        return $this->runReferenceProcedure();
    }

    public function loadAlphalist(Request $request)
    {
        return $this->runWithFilters($request, 'sproc_PHP_Inq_Alphalist');
    }

    private function runWithFilters(Request $request, string $procedure)
    {
        $data = $request->validate(['jsonData' => 'required|array']);

        return $this->runProcedure(
            $procedure,
            'Load',
            json_encode(['jsonData' => $data['jsonData']], JSON_THROW_ON_ERROR),
        );
    }

    private function runReferenceProcedure()
    {
        try {
            $results = DB::select('EXEC sproc_PHP_Ref_PayrollYearEnd');

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $exception) {
            Log::error('Payroll year-end references failed.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
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
            Log::error('Payroll year-end processing request failed.', [
                'procedure' => $procedure,
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
