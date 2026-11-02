<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RefEDController extends Controller
{
    private const PROCEDURE = 'sproc_PHP_REF_ED';

    public function index()
    {
        return $this->runProcedure('Load');
    }

    public function lookup(Request $request)
    {
        return $this->runProcedure('Lookup', $this->input($request));
    }

    public function get(Request $request)
    {
        return $this->runProcedure('Get', $this->keyInput($request));
    }

    public function upsert(Request $request)
    {
        $input = $this->input($request);
        $rules = [
            'edCode' => 'required|string|max:3', 'edName' => 'required|string|max:200',
            'edType' => 'nullable|string|in:EARN,DED', 'code' => 'nullable|string|max:15',
            'reportHdr' => 'nullable|string|max:200', 'reportField' => 'nullable|string|max:20',
            'order' => 'nullable|string|max:2', 'compHdmf' => 'nullable|string|max:5',
            'acctCode' => 'nullable|string|max:15', 'acctName' => 'nullable|string|max:200',
            'tax2316' => 'nullable|string|max:5', 'ntax2316' => 'nullable|string|max:5',
            'userCode' => 'required|string|max:50', 'action' => 'nullable|string|in:ADD,EDIT',
        ];
        foreach (['ctrp', 'deminimis', 'taxable', 'compSss', 'compPh', 'bonusFlag', 'includeBonexamt', 'includeMp13', 'active'] as $flag) {
            $rules[$flag] = 'nullable|string|in:Y,N';
        }
        $data = Validator::make($input, $rules)->validate();

        // Upsert assigns every column. Require the complete editable field set
        // so a partial request cannot accidentally erase existing settings.
        foreach (['edType', 'code', 'reportHdr', 'reportField', 'order', 'compHdmf', 'acctCode', 'acctName',
            'tax2316', 'ntax2316', 'ctrp', 'deminimis', 'taxable', 'compSss', 'compPh', 'bonusFlag',
            'includeBonexamt', 'includeMp13', 'active'] as $key) {
            if (!array_key_exists($key, $input)) {
                throw ValidationException::withMessages([$key => 'Include this field when saving an E/D Code.']);
            }
        }

        return $this->runProcedure('Upsert', $data);
    }

    public function checkDuplicate(Request $request)
    {
        return $this->runProcedure('CheckDuplicate', $this->keyInput($request));
    }

    public function checkInUsed(Request $request)
    {
        $data = $this->keyInput($request);
        try {
            $used = $this->isReferenced($data['edCode']);
            return response()->json([
                'success' => true,
                'isInUsed' => $used,
                'data' => [(object) ['result' => json_encode(['result' => $used ? '1' : '0'])]],
            ]);
        } catch (\Throwable $exception) {
            return $this->failure('CheckInUsed', $exception);
        }
    }

    public function delete(Request $request)
    {
        $data = $this->keyInput($request);
        $data = Validator::make($data, [
            'edCode' => 'required|string|max:3', 'userCode' => 'required|string|max:50',
        ])->validate();
        try {
            if ($this->isReferenced($data['edCode'])) {
                return response()->json([
                    'success' => false, 'message' => 'This E/D Code is already used by another record.',
                    'errormsg' => 'This E/D Code is already used by another record.', 'errorcount' => 1,
                ], 422);
            }
            return $this->runProcedure('Delete', $data);
        } catch (\Throwable $exception) {
            return $this->failure('Delete', $exception);
        }
    }

    private function input(Request $request): array
    {
        $input = $request->input('json_data', $request->input('jsonData', $request->input('PARAMS', $request->all())));
        if (is_string($input)) {
            try {
                $input = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw ValidationException::withMessages(['json_data' => 'A valid JSON object is required.']);
            }
        }
        if (!is_array($input)) {
            throw ValidationException::withMessages(['json_data' => 'An object is required.']);
        }
        $input = $input['json_data'] ?? $input['jsonData'] ?? $input;
        if (!is_array($input)) {
            throw ValidationException::withMessages(['json_data' => 'An object is required.']);
        }
        if (!array_key_exists('edCode', $input) && array_key_exists('ED_CODE', $input)) {
            $input['edCode'] = $input['ED_CODE'];
        }
        return $input;
    }

    private function keyInput(Request $request): array
    {
        return Validator::make($this->input($request), [
            'edCode' => 'required|string|max:3', 'userCode' => 'nullable|string|max:50',
        ])->validate();
    }

    private function isReferenced(string $edCode): bool
    {
        // The current procedure's CheckInUsed is a placeholder. Check actual
        // ED_CODE columns; identifiers come only from SQL Server metadata.
        $rows = DB::select(<<<'SQL'
DECLARE @edCode nvarchar(3) = ?, @sql nvarchar(max), @used bit = 0;
SELECT @sql = STRING_AGG(CAST(
    N'IF @used = 0 AND EXISTS (SELECT 1 FROM ' + QUOTENAME(s.name) + N'.' + QUOTENAME(t.name)
    + N' WHERE ' + QUOTENAME(c.name) + N' = @edCode) SET @used = 1;'
    AS nvarchar(max)), CHAR(10))
FROM sys.tables AS t
INNER JOIN sys.schemas AS s ON s.schema_id = t.schema_id
INNER JOIN sys.columns AS c ON c.object_id = t.object_id
WHERE UPPER(c.name) = 'ED_CODE' AND t.is_ms_shipped = 0
  AND t.object_id <> OBJECT_ID(N'dbo.REF_ED');
IF NULLIF(@sql, N'') IS NOT NULL
    EXEC sys.sp_executesql @sql, N'@edCode nvarchar(3), @used bit OUTPUT', @edCode, @used OUTPUT;
SELECT @used AS isInUsed;
SQL, [$edCode]);
        if (!isset($rows[0]->isInUsed)) {
            throw new \RuntimeException('Unable to verify E/D Code usage.');
        }
        return (bool) $rows[0]->isInUsed;
    }

    private function runProcedure(string $mode, ?array $input = null)
    {
        try {
            $rows = $input === null
                ? DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?', [$mode])
                : DB::select('EXEC ' . self::PROCEDURE . ' @mode = ?, @params = ?', [
                    $mode, json_encode(['json_data' => $input], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]);
            $errorCount = (int) ($rows[0]->errorcount ?? $rows[0]->errorCount ?? 0);
            $errorMessage = $rows[0]->errormsg ?? $rows[0]->errorMsg ?? '';
            return response()->json([
                'success' => $errorCount === 0, 'data' => $rows,
                'errormsg' => $errorMessage, 'errorcount' => $errorCount,
                'message' => $errorMessage,
            ], $errorCount > 0 ? 422 : 200);
        } catch (\Throwable $exception) {
            return $this->failure($mode, $exception);
        }
    }

    private function failure(string $mode, \Throwable $exception)
    {
        Log::error('E/D reference procedure failed.', ['mode' => $mode, 'error' => $exception->getMessage()]);
        return response()->json([
            'success' => false, 'message' => $exception->getMessage(),
            'errormsg' => $exception->getMessage(), 'errorcount' => 1,
        ], 500);
    }
}
