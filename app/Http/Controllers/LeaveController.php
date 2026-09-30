<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LOAD
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefLeave @mode = ?',
                ['Load']
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






    /*
    |--------------------------------------------------------------------------
    | LOOKUP
    |--------------------------------------------------------------------------
    */
    public function lookup(Request $request)
    {
        $request->validate([
            'PARAMS' => 'required|string',
        ]);

        $params = $request->input('PARAMS');

        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefLeave @mode = ?, @params = ?',
                ['Lookup', $params]
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










    /*
    |--------------------------------------------------------------------------
    | GET
    |--------------------------------------------------------------------------
    */
    public function get(Request $request)
    {
        $request->validate([
            'LV_CODE' => 'required|string',
        ]);

        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefLeave @mode = ?, @params = ?',
                ['Get', $request->LV_CODE]
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








    /*
    |--------------------------------------------------------------------------
    | UPSERT
    |--------------------------------------------------------------------------
    */
    public function upsert(Request $request)
    {
        /*
         * RefLeave.jsx currently sends:
         *
         * {
         *   "json_data": "{\"json_data\":{...}}"
         * }
         *
         * This controller also accepts the standard array form:
         *
         * {
         *   "json_data": {
         *      "lvCode": "VL",
         *      ...
         *   }
         * }
         */
        $request->validate([
            'json_data' => 'required',
        ]);

        $data = $request->input('json_data');

        try {
            if (is_string($data)) {
                $decoded = json_decode($data, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    return response()->json([
                        'errormsg' => 'Invalid json_data format.',
                        'errorcount' => 1,
                    ], 422);
                }

                /*
                 * If the JSX already supplied {"json_data": {...}},
                 * pass it directly to the stored procedure.
                 */
                if (array_key_exists('json_data', $decoded)) {
                    $params = json_encode(
                        $decoded,
                        JSON_UNESCAPED_UNICODE
                    );
                } else {
                    $params = json_encode(
                        ['json_data' => $decoded],
                        JSON_UNESCAPED_UNICODE
                    );
                }
            } else {
                /*
                 * Standard React object payload.
                 */
                $params = json_encode(
                    ['json_data' => $data],
                    JSON_UNESCAPED_UNICODE
                );
            }

            // Use select to get the result set (errormsg, errorcount)
            $result = DB::select(
                'EXEC sproc_PHP_RefLeave @mode = ?, @params = ?',
                ['Upsert', $params]
            );

            // Return the first row directly to React
            return response()->json([
                'errormsg' => $result[0]->errormsg ?? '',
                'errorcount' => $result[0]->errorcount ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Leave upsert failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'errormsg' => $e->getMessage(),
                'errorcount' => 1,
            ], 500);
        }
    }






    /*
    |--------------------------------------------------------------------------
    | CHECK IN USED
    |--------------------------------------------------------------------------
    */
    public function checkInUsed(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array',
            'json_data.lvCode' => 'required|string',
        ]);

        $params = json_encode(
            $validated,
            JSON_UNESCAPED_UNICODE
        );

        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefLeave @mode = ?, @params = ?',
                ['CheckInUsed', $params]
            );

            // Decode the internal JSON string from SQL for a cleaner API response
            $raw = $results[0]->result ?? '{"result":"0"}';
            $decoded = json_decode($raw, true);

            return response()->json([
                'success' => true,
                'isInUsed' => ($decoded['result'] ?? '0') === '1',

                // Keep the SPROC row structure available for RefLeave.jsx,
                // which currently reads response.data.data[0].result.
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }






    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE
    |--------------------------------------------------------------------------
    */
    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'json_data' => 'required|array',
            'json_data.lvCode' => 'required|string',
        ]);

        $params = json_encode(
            $validated,
            JSON_UNESCAPED_UNICODE
        );

        try {
            $results = DB::select(
                'EXEC sproc_PHP_RefLeave @mode = ?, @params = ?',
                ['CheckDuplicate', $params]
            );

            $raw = $results[0]->result ?? '{"result":"0"}';
            $decoded = json_decode($raw, true);

            return response()->json([
                'success' => true,
                'result' => $decoded['result'] ?? '0',

                // Keep compatibility with RefLeave.jsx.
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }






    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function delete(Request $request)
    {
        $request->validate([
            'json_data' => 'required|array',
            'json_data.lvCode' => 'required|string',
        ]);

        $data = $request->input('json_data');
        $code = $data['lvCode'] ?? null;

        if (!$code) {
            return response()->json([
                'success' => false,
                'message' => 'Leave Code is required.',
            ], 400);
        }

        try {
            // Wrap the array into the structure the SPROC expects
            $params = json_encode(
                ['json_data' => $data],
                JSON_UNESCAPED_UNICODE
            );

            // Use DB::select to get the row returned by the SPROC
            $result = DB::select(
                'EXEC sproc_PHP_RefLeave @mode = ?, @params = ?',
                ['Delete', $params]
            );

            $errorCount = $result[0]->errorcount ?? 0;
            $errorMsg = $result[0]->errormsg ?? '';

            if ($errorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg ?: 'Unable to delete Leave Code.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Leave delete failed', [
                'lvCode' => $code,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
