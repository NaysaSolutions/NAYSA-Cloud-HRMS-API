<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveCreditBalanceController extends Controller
{
    public function references()
    {
        return $this->run('References');
    }

    public function load(Request $request)
    {
        return $this->runWithFilters($request, 'Load');
    }

    public function recalculate(Request $request)
    {
        return $this->runWithFilters($request, 'Recalculate');
    }

    public function save(Request $request)
    {
        $data = $request->validate(['jsonData' => 'required|json']);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_Inq_LeaveCreditBalance @mode = ?, @params = ?',
                ['Save', $data['jsonData']],
            );

            return response()->json(['status' => 'success', 'data' => $results]);
        } catch (\Exception $exception) {
            Log::error('Leave credit balance save failed.', ['error' => $exception->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save leave credit balance: ' . $exception->getMessage(),
            ], 500);
        }
    }

    private function runWithFilters(Request $request, string $mode)
    {
        $data = $request->validate(['jsonData' => 'required|array']);

        return $this->run(
            $mode,
            json_encode(['jsonData' => $data['jsonData']], JSON_THROW_ON_ERROR),
        );
    }

    private function run(string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select('EXEC sproc_PHP_Inq_LeaveCreditBalance @mode = ?', [$mode])
                : DB::select('EXEC sproc_PHP_Inq_LeaveCreditBalance @mode = ?, @params = ?', [$mode, $params]);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }
}
