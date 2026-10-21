<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TimesheetController extends Controller
{
    private const SPROC = 'sproc_PHP_Tran_Timesheet';

    private function payload(Request $request): array
    {
        $payload = $request->input('json_data', $request->all());

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                abort(422, 'Invalid json_data JSON format.');
            }
            $payload = $decoded['json_data'] ?? $decoded;
        }

        if (is_array($payload) && isset($payload['json_data']) && is_array($payload['json_data'])) {
            $payload = $payload['json_data'];
        }

        return is_array($payload) ? $payload : [];
    }

    private function params(array $data): string
    {
        return json_encode(['json_data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function rows(string $mode, array $data): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select('EXEC '.self::SPROC.' @mode = ?, @params = ?', [$mode, $this->params($data)])
        );
    }

    private function ok(array $data = [], ?string $message = null)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $operation, Throwable $e)
    {
        Log::error("Timesheet {$operation} failed", ['message' => $e->getMessage()]);
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }

    private function common(array $data, bool $userRequired = false): void
    {
        Validator::make($data, [
            'tsId' => 'nullable|integer',
            'payFreq' => 'nullable|string|max:20',
            'cutOff' => 'nullable|string|max:20',
            'empNo' => 'nullable|string|max:50',
            'branchCode' => 'nullable|string|max:50',
            'deptCode' => 'nullable|string|max:50',
            'positionCode' => 'nullable|string|max:50',
            'groupCode' => 'nullable|string|max:50',
            'empStatus' => 'nullable|string|max:50',
            'clientCode' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:20',
            'posted' => 'nullable|string|max:1',
            'userCode' => ($userRequired ? 'required' : 'nullable').'|string|max:50',
        ])->validate();
    }

    public function load(Request $request)
    {
        $data = $this->payload($request);
        $this->common($data);
        try {
            return $this->ok($this->rows('LOAD', $data));
        } catch (Throwable $e) {
            return $this->fail('load', $e);
        }
    }

    public function generate(Request $request)
    {
        $data = $this->payload($request);
        $this->common($data, true);
        Validator::make($data, [
            'payFreq' => 'required|string|max:20',
            'cutOff' => 'required|string|max:20',
        ])->validate();

        try {
            $rows = $this->rows('GENERATE', $data);
            return $this->ok($rows, $rows[0]['message'] ?? 'Timesheet generation completed.');
        } catch (Throwable $e) {
            return $this->fail('generate', $e);
        }
    }

    public function recalculate(Request $request)
    {
        $data = $this->payload($request);
        $this->common($data, true);
        try {
            $rows = $this->rows('RECALCULATE', $data);
            return $this->ok($rows, $rows[0]['message'] ?? 'Timesheet recalculation completed.');
        } catch (Throwable $e) {
            return $this->fail('recalculate', $e);
        }
    }

    // GET returns five SQL Server result sets.
    public function get(Request $request)
    {
        $data = $this->payload($request);
        $this->common($data);

        Validator::make($data, [
            'tsId' => 'nullable|integer',
        ])->after(function ($validator) use ($data) {
            if (empty($data['tsId']) &&
                (empty($data['payFreq']) || empty($data['cutOff']) || empty($data['empNo']))) {
                $validator->errors()->add('tsId', 'Provide tsId or payFreq + cutOff + empNo.');
            }
        })->validate();

        try {
            $stmt = DB::connection()->getPdo()->prepare(
                'EXEC '.self::SPROC.' @mode = ?, @params = ?'
            );
            $stmt->execute(['GET', $this->params($data)]);

            $sets = [];
            do {
                if ($stmt->columnCount() > 0) {
                    $sets[] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            } while ($stmt->nextRowset());

            return response()->json([
                'success' => true,
                'data' => [
                    'header' => $sets[0][0] ?? null,
                    'daily' => $sets[1] ?? [],
                    'payItems' => $sets[2] ?? [],
                    'allocations' => $sets[3] ?? [],
                    'exceptions' => $sets[4] ?? [],
                ],
            ]);
        } catch (Throwable $e) {
            return $this->fail('get', $e);
        }
    }

    public function validateTimesheet(Request $request)
    {
        return $this->lifecycle($request, 'VALIDATE', 'validation');
    }

    public function finalize(Request $request)
    {
        return $this->lifecycle($request, 'FINALIZE', 'finalization');
    }

    private function lifecycle(Request $request, string $mode, string $operation)
    {
        $data = $this->payload($request);
        $this->common($data, true);

        Validator::make($data, ['tsId' => 'nullable|integer'])
            ->after(function ($validator) use ($data) {
                if (empty($data['tsId']) && (empty($data['payFreq']) || empty($data['cutOff']))) {
                    $validator->errors()->add('tsId', 'Provide tsId or payFreq + cutOff.');
                }
            })->validate();

        try {
            $rows = $this->rows($mode, $data);
            return $this->ok($rows, $rows[0]['message'] ?? "Timesheet {$operation} completed.");
        } catch (Throwable $e) {
            return $this->fail($operation, $e);
        }
    }

    public function unfinalize(Request $request)
    {
        $data = $this->payload($request);
        $this->common($data, true);
        Validator::make($data, ['tsId' => 'required|integer'])->validate();

        try {
            $rows = $this->rows('UNFINALIZE', $data);
            return $this->ok($rows, $rows[0]['message'] ?? 'Timesheet reopened.');
        } catch (Throwable $e) {
            return $this->fail('unfinalize', $e);
        }
    }

    public function loadPayItem(Request $request)
    {
        $data = $this->payload($request);
        $this->common($data);
        Validator::make($data, [
            'payFreq' => 'required|string|max:20',
            'cutOff' => 'required|string|max:20',
            'itemType' => 'required|string|in:LEAVE,OT,ND,leave,ot,nd',
        ])->validate();

        $data['itemType'] = strtoupper($data['itemType']);

        try {
            return $this->ok($this->rows('LOADPAYITEM', $data));
        } catch (Throwable $e) {
            return $this->fail('loadPayItem', $e);
        }
    }
}
