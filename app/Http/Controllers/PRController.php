<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericApiMail;

class PRController extends Controller
{
    
public function index(Request $request) {

    try {

        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');
      
        $results = DB::select(
            'EXEC sproc_PHP_PR @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_PR @mode = ?, @params = ?',
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



public function upsert(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Upsert';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_PR @mode = ?, @params = ?', [
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
                'message' => 'Error executing PR Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}   

    
public function cancel(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'Cancel';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_PR @mode = ?, @params = ?', [
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
                'message' => 'Error executing PR Upsert.',
                'details' => $e->getMessage()
            ], 500);
        }
}


public function history(Request $request) {

        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'History';

            // Call the stored procedure
            $results = DB::select('EXEC sproc_PHP_PR @mode = ?, @params = ?', [
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
                'message' => 'Error executing PR Upsert.',
                'details' => $e->getMessage()
            ], 500);
    }

}




public function getBranchItemBalance(Request $request)
{
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $params = json_encode(['json_data' => $validated['json_data']]);
            $mode = 'GetBranchBalance';

            // Call the stored procedure
            $result = DB::select('EXEC sproc_PHP_PR @mode = ?, @params = ?', [
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
                'message' => 'Error executing PR GetBranchBalance.',
                'details' => $e->getMessage()
            ], 500);
        }
}   




public function getPROpen(Request $request)
    {
        Log::info('getPROpen request', $request->all());

        // ✅ Validate json_data (same as your other endpoints)
        $validated = $request->validate([
            'json_data' => 'required|array'
        ]);

        try {
            $json = $validated['json_data'];

            $mode       = $json['mode'] ?? 'Header';
            $branchCode = $json['branchCode'] ?? null;
            $prTranType = $json['prTranType'] ?? null;
            $prId       = $json['prId'] ?? null;

            Log::info('getPROpen calling sproc', [
                'mode'       => $mode,
                'branchCode' => $branchCode,
                'prTranType' => $prTranType,
                'prId'       => $prId,
            ]);

            $rows = DB::select(
                'EXEC sproc_PHP_PR_Open @mode = ?, @branchCode = ?, @prTranType = ?, @prId = ?',
                [$mode, $branchCode, $prTranType, $prId]
            );

            // For PO Reference PR lookup, the frontend needs the Department Name.
            // Some versions of sproc_PHP_PR_Open only return the RC code, so enrich
            // Header rows here without changing the existing stored procedure result shape.
            if (strtoupper($mode) === 'HEADER') {
                $rows = $this->attachRcNameToPROpenRows($rows);
            }

            return response()->json([
                'success' => true,
                'data'    => $rows,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('getPROpen failed', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }




public function update(Request $request)
    {
        try {
            $branchCode = $request->input('branchCode');   // e.g. "HO"
            $poId       = $request->input('poId');         // PO_ID (GUID)
            $userCode   = $request->user()->USER_CODE
                        ?? $request->input('userCode', 'NSI');

            if (!$branchCode || !$poId) {
                return response()->json([
                    'success' => false,
                    'message' => 'branchCode and poId are required.',
                ], 422);
            }

            // Build the JSON payload expected by your sproc
            $payload = json_encode([
                'json_data' => [
                    'branchCode' => $branchCode,
                    'poId'       => $poId,
                    'userCode'   => $userCode,
                ],
            ]);

            // Call sproc_PHP_PO with mode = 'Update'
            $result = DB::connection('sqlsrv')->select(
                "EXEC sproc_PHP_PO @mode = :mode, @params = :params",
                [
                    'mode'   => 'Update',
                    'params' => $payload,
                ]
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }







public function getPRJO_OpenSummary(Request $request) {

   $jsonString = $request->input('PARAMS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PR @mode = ?, @params = ?',
            ['getPRJO_OpenSummary' ,$jsonString] 
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




public function getPRPO_OpenSummary(Request $request) {

   $jsonString = $request->input('PARAMS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PR @mode = ?, @params = ?',
            ['getPRPO_OpenSummary' ,$jsonString] 
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




public function getPRJO_OpenDetail(Request $request) {
    
    $jsonString = $request->input('json_data');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PR @mode = ?, @params = ?',
            ['getPRJO_OpenDetail' ,$jsonString] 
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



public function getPRPO_OpenDetail(Request $request) {
    
    $jsonString = $request->input('json_data');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PR @mode = ?, @params = ?',
            ['getPRPO_OpenDetail' ,$jsonString] 
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







public function getPRApproval(Request $request) {

   $jsonString = $request->input('PARAMS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_PR @mode = ?, @params = ?',
            ['GetApproval' ,$jsonString] 
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




public function approvePR(Request $request)
{
    $validated = $request->validate([
        'json_data' => 'required|array'
    ]);

    try {
        $params = json_encode([
            'json_data' => $validated['json_data']
        ]);

        $mode = 'Approve';

        // Execute stored procedure
        $result = DB::select(
            'EXEC sproc_PHP_PR @mode = ?, @params = ?',
            [$mode, $params]
        );

        // Default rows
        $rows = $result;

        /*
         * Handle JSON string response from SP
         * Example:
         * [
         *   (object)[
         *      'result' => '[{"emailTo":"...","subject":"...","body":"..."}]'
         *   ]
         * ]
         */
        if (isset($result[0]) && is_object($result[0])) {

            $firstRow = (array) $result[0];

            if (count($firstRow) === 1) {

                $firstValue = reset($firstRow);

                if (is_string($firstValue)) {

                    $decoded = json_decode($firstValue);

                    if (
                        json_last_error() === JSON_ERROR_NONE &&
                        is_array($decoded)
                    ) {
                        $rows = $decoded;
                    }
                }
            }
        }

        /*
         * If SP returns:
         * SELECT 'Success' AS result
         * then skip email sending
         */
        if (
            count($rows) === 1 &&
            isset($rows[0]->result) &&
            $rows[0]->result === 'Success'
        ) {
            return response()->json([
                'status' => 'success',
                'message' => 'PR approved successfully.',
                'data' => $rows,
                'mail_summary' => [
                    'sent_count' => 0,
                    'failed_count' => 0,
                    'sent' => [],
                    'failed' => [],
                ]
            ], 200);
        }

        $sentEmails = [];
        $failedEmails = [];

        foreach ($rows as $row) {

            $row = (object) $row;

            $emailTo = $row->emailTo ?? null;
            $subject = $row->subject ?? null;
            $body    = $row->body ?? null;

            // Validate required fields
            if (empty($emailTo) || empty($subject) || empty($body)) {

                $failedEmails[] = [
                    'emailTo' => $emailTo,
                    'reason' => 'Missing emailTo, subject, or body'
                ];

                continue;
            }

            // Validate email format
            if (!filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {

                $failedEmails[] = [
                    'emailTo' => $emailTo,
                    'reason' => 'Invalid email address'
                ];

                continue;
            }

            try {

                // Send email
                Mail::to($emailTo)
                    ->send(new GenericApiMail($subject, $body));

                $sentEmails[] = [
                    'emailTo' => $emailTo,
                    'subject' => $subject
                ];

            } catch (\Throwable $mailException) {

                $failedEmails[] = [
                    'emailTo' => $emailTo,
                    'reason' => $mailException->getMessage()
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'mail_summary' => [
                'sent_count' => count($sentEmails),
                'failed_count' => count($failedEmails),
                'sent' => $sentEmails,
                'failed' => $failedEmails,
            ]
        ], 200);

    } catch (\Throwable $e) {

        return response()->json([
            'status' => 'error',
            'message' => 'Error executing PR Approval.',
            'details' => $e->getMessage()
        ], 500);
    }
}




}








