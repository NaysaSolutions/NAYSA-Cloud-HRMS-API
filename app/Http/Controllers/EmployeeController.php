<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function index()
    {
        try {
            $rows = DB::select('EXEC sproc_PHP_Ref_Employee @mode = ?', ['Load']);
            return response()->json(['success' => true, 'data' => $rows], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function lookup(Request $request)
    {
        try {
            $params = $request->input('PARAMS', '{}');
            $rows = DB::select('EXEC sproc_PHP_Ref_Employee @mode = ?, @params = ?', ['Lookup', $params]);
            return response()->json(['success' => true, 'data' => $rows], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function get(Request $request)
    {
        $request->validate(['EMP_NO' => 'required|string']);
        try {
            $rows = DB::select('EXEC sproc_PHP_Ref_Employee @mode = ?, @params = ?', ['Get', $request->EMP_NO]);
            return response()->json(['success' => true, 'data' => $rows], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $request->validate(['json_data' => 'required|json']);
        try {
            $rows = DB::select('EXEC sproc_PHP_Ref_Employee @mode = ?, @params = ?', ['Upsert', $request->json_data]);
            $r0 = $rows[0] ?? null;
            $errorcount = (int)($r0->errorcount ?? 0);
            return response()->json([
                'success' => $errorcount === 0,
                'errorcount' => $errorcount,
                'errormsg' => (string)($r0->errormsg ?? ''),
                'data' => $rows,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Employee upsert failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function arrayParams(Request $request): string
    {
        $validated = $request->validate(['json_data' => 'required|array']);
        return json_encode(['json_data' => $validated['json_data']]);
    }

    public function delete(Request $request)
    {
        try {
            $rows = DB::select('EXEC sproc_PHP_Ref_Employee @mode = ?, @params = ?', ['Delete', $this->arrayParams($request)]);
            return response()->json(['success' => true, 'data' => $rows], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        try {
            $rows = DB::select('EXEC sproc_PHP_Ref_Employee @mode = ?, @params = ?', ['CheckDuplicate', $this->arrayParams($request)]);
            return response()->json(['success' => true, 'data' => $rows], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function checkInUsed(Request $request)
    {
        try {
            $rows = DB::select('EXEC sproc_PHP_Ref_Employee @mode = ?, @params = ?', ['CheckInUsed', $this->arrayParams($request)]);
            return response()->json(['success' => true, 'data' => $rows], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function uploadPhoto(Request $request)
    {
        $validated = $request->validate([
            'EMP_NO' => 'required|string|max:15',
            'EMP_PHOTO' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $empNo = $validated['EMP_NO'];
        if (!DB::table('REFMAST_EMPLOYEE')->where('EMP_NO', $empNo)->exists()) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }
        $file = $request->file('EMP_PHOTO');
        $directory = public_path('employee-photos');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return response()->json(['success' => false, 'message' => 'Unable to create photo directory.'], 500);
        }
        $filename = (string) \Illuminate\Support\Str::uuid() . '.' . $file->extension();
        $file->move($directory, $filename);
        $path = 'employee-photos/' . $filename;
        try {
            $old = DB::table('REFMAST_EMPLOYEE')->where('EMP_NO', $empNo)->value('EMP_PHOTO');
            DB::table('REFMAST_EMPLOYEE')->where('EMP_NO', $empNo)->update(['EMP_PHOTO' => $path]);
            if ($old && str_starts_with($old, 'employee-photos/')) {
                $oldPath = public_path($old);
                if (is_file($oldPath)) @unlink($oldPath);
            }
            return response()->json(['success' => true, 'employeePhoto' => $path, 'photoUrl' => asset($path)]);
        } catch (\Throwable $e) {
            @unlink(public_path($path));
            Log::error('Employee photo upload failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Unable to save employee photo.'], 500);
        }
    }

    public function getPhoto(Request $request)
    {
        $validated = $request->validate(['EMP_NO' => 'required|string|max:15']);
        $path = DB::table('REFMAST_EMPLOYEE')->where('EMP_NO', $validated['EMP_NO'])->value('EMP_PHOTO');
        return response()->json(['success' => true, 'data' => [
            'employeePhoto' => $path,
            'photoUrl' => $path ? asset($path) : null,
        ]]);
    }
}
