<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanController extends Controller
{
    private const PROCEDURE = 'sproc_PHP_Ref_Loan';

    public function index()
    {
        return $this->runProcedure('Load');
    }

    public function lookup(Request $request)
    {
        return $this->runProcedure('Lookup', $request->input('PARAMS', ''));
    }

    public function get(Request $request)
    {
        $data = $request->validate(['LOAN_CODE' => 'required|string|max:15']);

        return $this->runProcedure('Get', $data['LOAN_CODE']);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate(['json_data' => 'required']);
        $params = is_array($data['json_data'])
            ? json_encode(['json_data' => $data['json_data']], JSON_THROW_ON_ERROR)
            : $data['json_data'];

        return $this->runProcedure('Upsert', $params);
    }

    public function delete(Request $request)
    {
        return $this->runArrayMode($request, 'Delete');
    }

    public function checkDuplicate(Request $request)
    {
        return $this->runArrayMode($request, 'CheckDuplicate');
    }

    public function checkInUsed(Request $request)
    {
        return $this->runArrayMode($request, 'CheckInUsed');
    }

    private function runArrayMode(Request $request, string $mode)
    {
        $data = $request->validate(['json_data' => 'required|array']);
        $params = json_encode(['json_data' => $data['json_data']], JSON_THROW_ON_ERROR);

        return $this->runProcedure($mode, $params);
    }

    private function runProcedure(string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?', [$mode])
                : DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?, @params = ?', [$mode, $params]);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Throwable $exception) {
            Log::error('Loan reference procedure failed.', [
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
