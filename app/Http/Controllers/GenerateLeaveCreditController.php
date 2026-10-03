<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateLeaveCreditController extends Controller
{
    public function references()
    {
        return $this->run('References');
    }

    public function generate(Request $request)
    {
        $data = $request->validate(['jsonData' => 'required|array', 'jsonData.asOfDate' => 'required|date']);
        return $this->run('Generate', json_encode(['jsonData' => $data['jsonData']], JSON_THROW_ON_ERROR));
    }

    public function save(Request $request)
    {
        $data = $request->validate(['jsonData' => 'required|json']);
        try {
            $results = DB::select('EXEC sproc_PHP_Generate_LeaveCredit @mode = ?, @params = ?', ['Save', $data['jsonData']]);
            return response()->json(['status' => 'success', 'data' => $results]);
        } catch (\Exception $e) {
            Log::error('Generated leave credit save failed.', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to save transaction: ' . $e->getMessage()], 500);
        }
    }

    private function run(string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select('EXEC sproc_PHP_Generate_LeaveCredit @mode = ?', [$mode])
                : DB::select('EXEC sproc_PHP_Generate_LeaveCredit @mode = ?, @params = ?', [$mode, $params]);
            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
