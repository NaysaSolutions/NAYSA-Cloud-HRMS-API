<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericApiMail;

class BankReconController extends Controller
{
    /**
     * Main Bank Reconciliation endpoint.
     *
     * Expected payload:
     * {
     *   "mode": "Load",
     *   "params": {
     *     "json_data": {
     *       "bkId": "",
     *       "branchCode": "00001",
     *       "cutOff": "202604",
     *       "bankCode": "BPI001",
     *       "userCode": "ADMIN",
     *       "userName": "Administrator",
     *       "pcName": "WEB",
     *       "macAddress": "",
     *       "dt1": []
     *     }
     *   }
     * }
     */
    public function process(Request $request)
    {
        $mode = $request->input('mode');
        $params = $request->input('params');

        if (empty($mode)) {
            return response()->json([
                'success' => false,
                'message' => 'Mode is required.',
            ], 422);
        }

        if (empty($params)) {
            $params = [
                'json_data' => []
            ];
        }

        /*
         * If frontend sends json_data directly instead of params.json_data,
         * normalize it here.
         */
        if (!isset($params['json_data']) && $request->has('json_data')) {
            $params = [
                'json_data' => $request->input('json_data')
            ];
        }

        /*
         * Ensure user fields exist.
         * Adjust these based on your current auth/session structure.
         */
        $params['json_data']['userCode'] = $params['json_data']['userCode'] ?? optional($request->user())->user_code ?? optional($request->user())->email ?? '';
        $params['json_data']['userName'] = $params['json_data']['userName'] ?? optional($request->user())->name ?? '';
        $params['json_data']['pcName'] = $params['json_data']['pcName'] ?? gethostname();
        $params['json_data']['macAddress'] = $params['json_data']['macAddress'] ?? '';

        $jsonParams = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $rows = DB::select(
                "exec dbo.sproc_PHP_BK @mode = ?, @params = ?",
                [$mode, $jsonParams]
            );

            $result = $rows[0]->result ?? null;

            /*
             * Some modes return plain columns like:
             * bkId, message, errorMsg, errorCount
             * instead of result.
             */
            if ($result === null && count($rows) > 0) {
                $firstRow = (array) $rows[0];

                if (array_key_exists('errorMsg', $firstRow)) {
                    return response()->json([
                        'success' => false,
                        'message' => $firstRow['errorMsg'],
                        'errorCount' => $firstRow['errorCount'] ?? 1,
                        'data' => $firstRow,
                    ], 200);
                }

                return response()->json([
                    'success' => true,
                    'data' => $firstRow,
                ], 200);
            }

            $decoded = null;

            if (is_string($result) && $result !== '') {
                $decoded = json_decode($result, true);
            }

            return response()->json([
                'success' => true,
                'data' => $decoded ?? $result,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Bank Recon process failed', [
                'mode' => $mode,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Load Bank Recon working records.
     */
    public function load(Request $request)
    {
        return $this->callMode($request, 'Load');
    }


    /**
     * Get Bank Recon by bkId or cutOff + bankCode.
     */
    public function get(Request $request)
    {
        return $this->callMode($request, 'Get');
    }


    /**
     * Save Check Voucher / Deposit selected status and clear date.
     */
    public function saveCheck(Request $request)
    {
        return $this->callMode($request, 'SaveCheck');
    }


    /**
     * Generate Bank Reconciliation Summary.
     */
    public function generateBankRecon(Request $request)
    {
        return $this->callMode($request, 'GenerateBankRecon');
    }


    /**
     * Save Bank Reconciliation Summary.
     */
    public function saveBankRecon(Request $request)
    {
        return $this->callMode($request, 'SaveBankRecon');
    }


    /**
     * Post Bank Reconciliation.
     */
    public function post(Request $request)
    {
        return $this->callMode($request, 'Post');
    }


    /**
     * Unpost Bank Reconciliation.
     */
    public function unpost(Request $request)
    {
        return $this->callMode($request, 'Unpost');
    }


    /**
     * Clear Bank Reconciliation working records.
     */
    public function clear(Request $request)
    {
        return $this->callMode($request, 'Clear');
    }


    /**
     * Load Bank Recon History.
     */
    public function history(Request $request)
    {
        return $this->callMode($request, 'History');
    }


    /**
     * Find Bank Recon documents.
     */
    public function find(Request $request)
    {
        return $this->callMode($request, 'Find');
    }


    /**
     * Email Bank Reconciliation Report with PDF and Excel attachments.
     *
     * Expected multipart/form-data:
     * - to: comma/semicolon separated email addresses
     * - cc: optional comma/semicolon separated email addresses
     * - subject: email subject
     * - body: HTML body
     * - attachments[]: PDF / Excel files
     */
    public function emailReport(Request $request)
    {
        $request->validate([
            'to' => ['required', 'string'],
            'cc' => ['nullable', 'string'],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $to = $this->parseEmailList($request->input('to'));
        $cc = $this->parseEmailList($request->input('cc'));
        $subject = $request->input('subject');
        $body = $request->input('body');

        $attachments = $request->file('attachments', []);
        if ($attachments instanceof \Illuminate\Http\UploadedFile) {
            $attachments = [$attachments];
        }

        if (count($attachments) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Report attachments are required. Please generate the PDF and Excel files before sending.',
            ], 422);
        }

        if (count($to) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient email address is required.',
            ], 422);
        }

        try {
            $mail = new GenericApiMail($subject, $body);

            foreach ($attachments as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }

                $mail->attachData(
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName(),
                    [
                        'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
                    ]
                );
            }

            Mail::to($to)
                ->cc($cc)
                ->send($mail);

            return response()->json([
                'success' => true,
                'message' => 'Bank Reconciliation Report emailed successfully.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Bank Recon email report failed', [
                'to' => $to,
                'cc' => $cc,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Parse comma / semicolon separated email string.
     */
    private function parseEmailList(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return collect(preg_split('/[;,]+/', $value))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }


    /**
     * Internal helper to call one mode using the same payload format.
     */
    private function callMode(Request $request, string $mode)
    {
        $payload = $request->all();

        $payload['mode'] = $mode;

        if (!isset($payload['params'])) {
            if (isset($payload['json_data'])) {
                $payload['params'] = [
                    'json_data' => $payload['json_data']
                ];
            } else {
                $payload['params'] = [
                    'json_data' => $payload
                ];
            }
        }

        $newRequest = new Request($payload);

        if ($request->user()) {
            $newRequest->setUserResolver(function () use ($request) {
                return $request->user();
            });
        }

        return $this->process($newRequest);
    }
}
