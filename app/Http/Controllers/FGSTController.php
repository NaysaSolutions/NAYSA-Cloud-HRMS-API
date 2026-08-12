<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FGSTController extends Controller
{
     
    public function index(Request $request) {
        try {
            $allData = $request->all();
            $payload = isset($allData['json_data']) ? $allData['json_data'] : $allData;
            $params = json_encode(['json_data' => $payload], JSON_UNESCAPED_UNICODE);
          
            $results = DB::select(
                'EXEC sproc_PHP_FGST @mode = ?, @params = ?',
                ['get' ,$params] 
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

    public function get(Request $request) {
        $jsonData = $request->all(); 
        $jsonString = json_encode($jsonData); 

        try {
            $results = DB::select(
                'EXEC sproc_PHP_FGST @mode = ?, @params = ?',
                ['Get' ,$jsonString] 
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

    public function posting(Request $request) {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_FGST @mode = ?',
                ['Posting'] 
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

   public function upsert(Request $request)
{
    try {
        $allData = $request->all();

        $payload = isset($allData['json_data']) ? $allData['json_data'] : $allData;
        $payload = $this->normalizeUpsertPayload($payload);
        $params = json_encode(['json_data' => $payload], JSON_UNESCAPED_UNICODE);

        Log::info('FGST Upsert Params:', [
            'params' => $params
        ]);

        $result = DB::select('EXEC sproc_PHP_FGST @mode = ?, @params = ?', [
            'Upsert',
            $params
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $result
        ], 200);

    } catch (\Throwable $e) {
        Log::error('Error executing FGST Upsert:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Error executing FGST Upsert.',
            'details' => $e->getMessage()
        ], 500);
    }
}

    private function normalizeUpsertPayload($payload)
    {
        if (!is_array($payload)) {
            return $payload;
        }

        if (!isset($payload['dt1']) || !is_array($payload['dt1'])) {
            return $payload;
        }

        foreach ($payload['dt1'] as &$row) {
            if (!is_array($row) || !isset($row['uniqueKey']) || $row['uniqueKey'] === null) {
                continue;
            }

            $row['uniqueKey'] = substr((string) $row['uniqueKey'], 0, 20);
        }

        unset($row);

        return $payload;
    }

  public function finalize(Request $request) {

    try {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        $params = json_encode(['json_data' => $validated['json_data']], JSON_UNESCAPED_UNICODE);

        $results = DB::select(
            'EXEC sproc_PHP_Posting_FGST @mode = ?, @params = ?',
            ['Finalize', $params]
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);

    } catch (\Exception $e) {

        /*
            SQL Server message SQLSTATE[01003] is only a warning:
            "Null value is eliminated by an aggregate or other SET operation."

            Some ODBC/PDO setups throw it as an exception and Laravel returns HTTP 500,
            even if the stored procedure already completed and the FGST document was posted.

            This block verifies the selected FGST document status. If already finalized,
            return success so the UI can proceed.
        */
        $message = $e->getMessage();

        if (str_contains($message, 'SQLSTATE[01003]') || str_contains($message, 'Null value is eliminated by an aggregate')) {
            try {
                $payload = $request->input('json_data', []);
                $rows = $payload['dt1'] ?? $payload['selectedData'] ?? $payload['selectedRows'] ?? $payload['data'] ?? [];

                if (is_array($rows) && count($rows) > 0) {
                    $groupIds = [];

                    foreach ($rows as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        $groupId = $row['groupId'] ?? $row['fgstId'] ?? $row['documentID'] ?? $row['docId'] ?? null;

                        if ($groupId) {
                            $groupIds[] = $groupId;
                        }
                    }

                    $groupIds = array_values(array_unique($groupIds));

                    if (count($groupIds) > 0) {
                        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));

                        $posted = DB::select(
                            "SELECT fgst_id, fgst_no, branch_code, fgst_status
                             FROM fgst_hd
                             WHERE fgst_id IN ($placeholders)
                               AND ISNULL(fgst_status, '') = 'F'",
                            $groupIds
                        );

                        if (count($posted) === count($groupIds)) {
                            return response()->json([
                                'success' => true,
                                'warning' => 'SQL Server returned a null aggregate warning, but the FGST transaction was already posted successfully.',
                                'data' => [[
                                    'result' => 'The following FGST Transactions have been posted successfully.'
                                ]],
                                'posted' => $posted
                            ], 200);
                        }
                    }
                }
            } catch (\Throwable $verifyError) {
                Log::warning('FGST finalize warning verification failed.', [
                    'message' => $verifyError->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => $message,
        ], 500);
    }
}




    public function generateGL(Request $request)
    {
        try {
            $allData = $request->all();
            $payload = isset($allData['json_data']) ? $allData['json_data'] : $allData;
            
            if (!$payload) {
                return response()->json(['error' => 'Missing json_data payload'], 400);
            }
            $jsonString = json_encode(['json_data' => $payload], JSON_UNESCAPED_UNICODE);

            $results = DB::select("EXEC sproc_PHP_FGST @mode = ?, @params = ?", [
                'GenerateEntries',
                $jsonString
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing sproc_PHP_FGST GenerateEntries: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate entries.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request)
    {
        try {
            $allData = $request->all();
            $payload = isset($allData['json_data']) ? $allData['json_data'] : $allData;
            $params = json_encode(['json_data' => $payload], JSON_UNESCAPED_UNICODE);
            
            $mode = 'Cancel';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_FGST @mode = ?, @params = ?', [
                $mode,
                $params
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $result
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing MSST Cancel.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request) {
        try {
            $allData = $request->all();
            $payload = isset($allData['json_data']) ? $allData['json_data'] : $allData;
            $params = json_encode(['json_data' => $payload], JSON_UNESCAPED_UNICODE);
            
            $mode = 'History';

            // Call the stored procedure
            $results = DB::select('EXEC sproc_PHP_FGST @mode = ?, @params = ?', [
                $mode,
                $params
            ]);
       
            return response()->json([
                'status' => 'success',
                'data' => $results
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing MSST History.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function find(Request $request) {
        try {
            $allData = $request->all();
            $payload = isset($allData['json_data']) ? $allData['json_data'] : $allData;
            $params = json_encode(['json_data' => $payload], JSON_UNESCAPED_UNICODE);
            
            $mode = 'Find';

            $results = DB::select('EXEC sproc_PHP_FGST @mode = ?, @params = ?', [
                $mode,
                $params
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error executing FGST Find.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
