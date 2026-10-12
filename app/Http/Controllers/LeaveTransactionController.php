<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LeaveTransactionController extends Controller
{
    private const PROC = 'dbo.sproc_PHP_Tran_Leave';

    private function input(Request $request): array
    {
        $value = $request->input('json_data', $request->all());
        if (is_string($value)) {
            $value = json_decode($value, true);
            if (!is_array($value)) abort(422, 'Invalid json_data JSON.');
        }
        return is_array($value['json_data'] ?? null) ? $value['json_data'] : (is_array($value) ? $value : []);
    }

    public function references(Request $r) { return $this->run($r, 'REFERENCES'); }
    public function employees(Request $r) { return $this->run($r, 'EMPLOYEES'); }
    public function load(Request $r) { return $this->run($r, 'LOAD'); }
    public function get(Request $r) { return $this->run($r, 'GET'); }
    public function upsert(Request $r) { return $this->run($r, 'UPSERT'); }
    public function delete(Request $r) { return $this->run($r, 'DELETE'); }
    public function validateLeave(Request $r) { return $this->run($r, 'VALIDATE'); }
    public function finalizeLeave(Request $r) { return $this->run($r, 'FINALIZE'); }
    public function unfinalizeLeave(Request $r) { return $this->run($r, 'UNFINALIZE'); }

    private function run(Request $request, string $mode)
    {
        $mode = strtoupper($mode);
        $allowed = ['REFERENCES','EMPLOYEES','LOAD','GET','UPSERT','DELETE','VALIDATE','FINALIZE','UNFINALIZE'];
        if (!in_array($mode, $allowed, true)) abort(404);
        $data = $this->input($request);
        $rules = [
            'payFreq' => 'nullable|string|max:50', 'cutOff' => 'nullable|string|max:8',
            'empNo' => 'nullable|string|max:15', 'branchCode'=>'nullable|string|max:15',
            'clientCode'=>'nullable|string|max:70', 'groupCode'=>'nullable|string|max:15',
            'deptCode'=>'nullable|string|max:15', 'positionCode'=>'nullable|string|max:100',
            'empStatus'=>'nullable|string|max:15', 'detailId'=>'nullable|integer|min:1',
        ];
        if (in_array($mode, ['EMPLOYEES','LOAD','GET','UPSERT','VALIDATE','FINALIZE','UNFINALIZE'],true)) {
            $rules['payFreq']='required|string|max:50'; $rules['cutOff']='required|string|max:8';
        }
        if ($mode==='UPSERT') {
            $rules=array_merge($rules,[
                'empNo'=>'required|string|max:15','lvCode'=>'required|string|max:10',
                'startDate'=>'required|date_format:Y-m-d','endDate'=>'required|date_format:Y-m-d|after_or_equal:startDate',
                'lvHours'=>'required|numeric|gt:0','lvDays'=>'required|numeric|gt:0',
                'hourRate'=>'required|numeric|min:0','dailyRate'=>'nullable|numeric|min:0',
                'remarks'=>'nullable|string|max:1000',
            ]);
        }
        if ($mode==='DELETE') $rules['detailId']='required|integer|min:1';
        Validator::make($data,$rules)->validate();
        // IMPORTANT: replace with your actual role/permission middleware for edit/finalize/reopen.
        if (in_array($mode,['UPSERT','DELETE','FINALIZE','UNFINALIZE'],true)
            && !$request->user()) abort(401);
        // NEVER trust userCode supplied by browser. Resolve authenticated identity here.
        if ($request->user()) $data['userCode'] = (string) ($request->user()->user_code ?? $request->user()->id);
        elseif (!in_array($mode,['REFERENCES','EMPLOYEES','LOAD','GET'],true)) abort(401);
        try {
            $params=json_encode(['json_data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $rows=DB::select('EXEC '.self::PROC.' @mode = ?, @params = ?',[$mode,$params]);
            return response()->json(['success'=>true,'data'=>$rows,'message'=>$rows[0]->message ?? 'OK']);
        } catch (Throwable $e) {
            Log::error('Leave transaction failed',['mode'=>$mode,'error'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>$e->getMessage()],422);
        }
    }
}
