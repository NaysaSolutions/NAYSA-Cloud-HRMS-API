<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericApiMail;

class JOController extends Controller
{
    
public function index(Request $request) {

    try {

        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');
      
        $results = DB::select(
            'EXEC sproc_PHP_JO @mode = ?, @params = ?',
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
            'EXEC sproc_PHP_JO @mode = ?, @params = ?',
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
            $result = DB::select('EXEC sproc_PHP_JO @mode = ?, @params = ?', [
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
                'message' => 'Error executing JO Upsert.',
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
            $result = DB::select('EXEC sproc_PHP_JO @mode = ?, @params = ?', [
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
                'message' => 'Error executing JO Upsert.',
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
            $results = DB::select('EXEC sproc_PHP_JO @mode = ?, @params = ?', [
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
                'message' => 'Error executing JO Upsert.',
                'details' => $e->getMessage()
            ], 500);
    }

}




public function getJOApproval(Request $request) {

   $jsonString = $request->input('PARAMS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_JO @mode = ?, @params = ?',
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




public function approveJO(Request $request)
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
            'EXEC sproc_PHP_JO @mode = ?, @params = ?',
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








