<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveCreditController extends Controller
{
    public function index() { return $this->run('Load'); }

    public function lookup(Request $request)
    {
        $data = $request->validate(['params' => 'required|json']);
        return $this->run('Lookup', $data['params']);
    }

    public function get(Request $request)
    {
        $data = $request->validate(['leaveId' => 'required|string|max:20']);
        return $this->run('Get', $this->wrap($data));
    }

    public function checkDuplicate(Request $request) { return $this->arrayMode($request, 'CheckDuplicate'); }
    public function checkInUsed(Request $request) { return $this->arrayMode($request, 'CheckInUsed'); }
    public function delete(Request $request) { return $this->arrayMode($request, 'Delete'); }

    public function upsert(Request $request)
    {
        $data = $request->validate(['jsonData' => 'required|json']);
        try {
            $results = DB::select('EXEC sproc_PHP_Ref_LeaveCredit @mode = ?, @params = ?', ['Upsert', $data['jsonData']]);
            return response()->json(['status' => 'success', 'data' => $results]);
        } catch (\Exception $e) {
            Log::error('Leave credit save failed.', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to save transaction: ' . $e->getMessage()], 500);
        }
    }

    private function arrayMode(Request $request, string $mode)
    {
        $data = $request->validate(['jsonData' => 'required|array', 'jsonData.leaveId' => 'required|string|max:20']);
        return $this->run($mode, $this->wrap($data['jsonData']));
    }

    private function run(string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select('EXEC sproc_PHP_Ref_LeaveCredit @mode = ?', [$mode])
                : DB::select('EXEC sproc_PHP_Ref_LeaveCredit @mode = ?, @params = ?', [$mode, $params]);
            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function wrap(array $data): string
    {
        return json_encode(['jsonData' => $data], JSON_THROW_ON_ERROR);
    }
}
