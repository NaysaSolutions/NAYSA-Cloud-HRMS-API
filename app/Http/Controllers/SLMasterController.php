<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SLMasterController extends Controller
{
    private function successResponse($data = [], $message = 'Success', $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function errorResponse(\Throwable $e, $message = 'Request failed', $status = 500)
    {
        Log::error($message, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], $status);
    }

    private function normalizeJsonData(Request $request): array
    {
        $jsonData = $request->input('json_data');

        if (is_string($jsonData)) {
            $decoded = json_decode($jsonData, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (is_array($jsonData)) {
            return ['json_data' => $jsonData];
        }

        return [];
    }

    private function execSproc(string $mode, $params = null)
    {
        return DB::select(
            'EXEC sproc_PHP_SLMast @mode = ?, @params = ?',
            [$mode, $params]
        );
    }

    public function slType(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_SLMast @mode = ?',
                ['Load_slType']
            );

            return $this->successResponse($results);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to load SL Type list.');
        }
    }

    public function sLMast(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_SLMast @mode = ?',
                ['Load_slMast']
            );

            return $this->successResponse($results);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to load SL Master list.');
        }
    }

    public function sLCoa(Request $request)
    {
        try {
            $mode = $request->get('mode', 'Load_slCoa');

            $params = json_encode([
                'json_data' => [
                    'slTypeCode' => $request->get('slTypeCode'),
                ]
            ]);

            $results = $this->execSproc($mode, $params);

            return response()->json([
                'success' => true,
                'result' => $results[0]->result ?? '[]',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to load SL-COA matching.');
        }
    }

    public function upsertSLMast(Request $request)
    {
        try {
            $payload = $this->normalizeJsonData($request);

            $results = $this->execSproc(
                'Upsert_slMast',
                json_encode($payload)
            );

            return $this->successResponse($results, 'SL Master saved successfully.');
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to save SL Master.');
        }
    }

    public function upsertSLType(Request $request)
    {
        try {
            $payload = $this->normalizeJsonData($request);

            $results = $this->execSproc(
                'Upsert_slType',
                json_encode($payload)
            );

            return $this->successResponse($results, 'SL Type saved successfully.');
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to save SL Type.');
        }
    }

    public function upsertSLTypeGLMatching(Request $request)
    {
        try {
            $payload = $this->normalizeJsonData($request);

            if (isset($payload['json_data']) && is_array($payload['json_data'])) {
                $payload['json_data'] = [
                    'slTypeCode' => $payload['json_data']['slTypeCode'] ?? '',
                    'acctCodes'  => $payload['json_data']['acctCodes'] ?? [],
                    'userCode'   => $payload['json_data']['userCode'] ?? '',
                ];
            }

            $results = $this->execSproc(
                'Upsert_slTypeGLMatching',
                json_encode($payload)
            );

            return $this->successResponse($results, 'SL-GL Matching saved successfully.');
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to save SL-GL Matching.');
        }
    }

    public function deleteSLMast(Request $request)
    {
        try {
            $payload = $this->normalizeJsonData($request);

            $results = $this->execSproc(
                'Delete_slMast',
                json_encode($payload)
            );

            return $this->successResponse($results, 'SL Master deleted successfully.');
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to delete SL Master.');
        }
    }

    // public function deleteSLType(Request $request)

    // {
    //     try {
    //         $payload = $this->normalizeJsonData($request);

    //         $results = $this->execSproc(
    //             'Delete_slType',
    //             json_encode($payload)
    //         );

    //         return $this->successResponse($results, 'SL Type deleted successfully.');
    //     } catch (\Throwable $e) {
    //         return $this->errorResponse($e, 'Failed to delete SL Type.');
    //     }
    // }

    
public function deleteSLType(Request $request) {

    try {

      $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']]);
      

        $results = DB::select(
            'EXEC sproc_PHP_SLMast @mode = ?, @params = ?',
            ['Delete_slType', $params]
        );

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


    public function lookup(Request $request)
    {
        try {
            $paramsString = $request->input('PARAMS');
            $params = json_decode($paramsString, true);

            $results = DB::select(
                'EXEC sproc_PHP_SLMast @mode = ?, @params = ?',
                ['Lookup', $params['search'] ?? '']
            );

            return $this->successResponse($results);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to lookup SL.');
        }
    }

    public function get(Request $request)
    {
        $request->validate([
            'SL_CODE' => 'required|string',
        ]);

        try {
            $params = $request->input('SL_CODE');

            $results = DB::select(
                'EXEC sproc_PHP_SLMast @mode = ?, @params = ?',
                ['Get', $params]
            );

            return $this->successResponse($results);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to get SL record.');
        }
    }



public function checkInUsedSLMast(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        // $params = json_encode(['json_data' => $validated['json_data']]);
        $params = json_encode($validated);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_SLMast @mode = ?, @params = ?',
            ['CheckInUsedSLMast' ,$params] 
        );

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

public function checkInUsedSLType(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        // $params = json_encode(['json_data' => $validated['json_data']]);
        $params = json_encode($validated);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_SLMast @mode = ?, @params = ?',
            ['CheckInUsedSLType' ,$params] 
        );

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






public function checkDuplicateSLMast(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        // $params = json_encode(['json_data' => $validated['json_data']]);
        $params = json_encode($validated);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_SLMast @mode = ?, @params = ?',
            ['CheckDuplicateSLMast' ,$params] 
        );

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



public function checkDuplicateSLType(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        // $params = json_encode(['json_data' => $validated['json_data']]);
        $params = json_encode($validated);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_SLMast @mode = ?, @params = ?',
            ['CheckDuplicateSLType' ,$params] 
        );

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

}