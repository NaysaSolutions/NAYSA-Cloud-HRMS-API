<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveLedgerController extends Controller
{
    public function references()
    {
        return $this->run('References');
    }

    public function load(Request $request)
    {
        return $this->runWithFilters($request, 'Load');
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
                ? DB::select('EXEC sproc_PHP_Inq_LeaveLedger @mode = ?', [$mode])
                : DB::select('EXEC sproc_PHP_Inq_LeaveLedger @mode = ?, @params = ?', [$mode, $params]);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }
}
