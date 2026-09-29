<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankInfoController extends Controller
{
    public function index()
    {
        return $this->select('Load');
    }




    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'params' => 'required|json',
        ]);

        return $this->select('Lookup', $validated['params']);
    }




    public function get(Request $request)
    {
        $validated = $request->validate($this->keyRules());

        return $this->select('Get', $this->wrap($validated));
    }




    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.bankCode' => 'required|string|max:200',
            'jsonData.compCode' => 'required|string|max:20',
            'jsonData.compAcct' => 'required|string|max:200',
        ]);

        return $this->select('CheckDuplicate', $this->wrap($validated['jsonData']));
    }




    public function checkInUsed(Request $request)
    {
        $validated = $request->validate([
            'jsonData' => 'required|array',
        ]);

        return $this->select('CheckInUsed', $this->wrap($validated['jsonData']));
    }




    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'jsonData' => 'required|json',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_Ref_BankInfo @mode = ?, @params = ?',
                ['Upsert', $validated['jsonData']]
            );

            return response()->json([
                'status' => 'success',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Bank information save failed.', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save transaction: ' . $e->getMessage(),
            ], 500);
        }
    }




    public function delete(Request $request)
    {
        $validated = $request->validate([
            'jsonData' => 'required|array',
            'jsonData.bankCode' => 'required|string|max:200',
            'jsonData.compCode' => 'required|string|max:20',
            'jsonData.compAcct' => 'required|string|max:200',
        ]);

        return $this->select('Delete', $this->wrap($validated['jsonData']));
    }




    private function select(string $mode, ?string $params = null)
    {
        try {
            $results = $params === null
                ? DB::select('EXEC sproc_PHP_Ref_BankInfo @mode = ?', [$mode])
                : DB::select('EXEC sproc_PHP_Ref_BankInfo @mode = ?, @params = ?', [$mode, $params]);

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function keyRules(): array
    {
        return [
            'bankCode' => 'required|string|max:200',
            'compCode' => 'required|string|max:20',
            'compAcct' => 'required|string|max:200',
        ];
    }

    private function wrap(array $data): string
    {
        return json_encode(['jsonData' => $data], JSON_THROW_ON_ERROR);
    }
}
