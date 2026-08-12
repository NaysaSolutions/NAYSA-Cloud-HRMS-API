<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PRInqController extends Controller
{
    public function getPRInquiry(Request $request)
    {
        try {
            $jsonData = $request->query('json_data', []);

            // Accept either a nested query object or a JSON-encoded query value.
            if (is_string($jsonData)) {
                $jsonData = json_decode($jsonData, true) ?? [];
            }

            if (!is_array($jsonData)) {
                $jsonData = [];
            }

            $startingDate = $jsonData['startingDate'] ?? $jsonData['fromDate'] ?? '';
            $endingDate = $jsonData['endingDate'] ?? $jsonData['toDate'] ?? '';

            if ($startingDate !== '' && $endingDate !== '' && $startingDate > $endingDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'From Date must not be later than To Date.',
                ], 422);
            }

            $params = [
                'json_data' => [
                    'branchCode'     => $jsonData['branchCode'] ?? '',
                    'itemCode'       => $jsonData['itemCode'] ?? '',
                    'prStatus'       => $jsonData['prStatus'] ?? '',
                    'startingDate'   => $startingDate,
                    'endingDate'     => $endingDate,
                    'startingCutoff' => $jsonData['startingCutoff'] ?? '',
                    'endingCutoff'   => $jsonData['endingCutoff'] ?? '',
                    'rcCode'         => $jsonData['rcCode'] ?? '',
                    'vendCode'       => $jsonData['vendCode'] ?? '',
                    'invType'        => $jsonData['invType'] ?? '',
                ],
            ];

            $result = DB::select(
                'EXEC dbo.sproc_PHP_PR_Inq @_params = ?',
                [json_encode($params)]
            );

            $raw = $result[0]->result ?? '[]';
            $data = json_decode($raw, true);

            if (!is_array($data)) {
                throw new \RuntimeException('The PR Inquiry procedure returned invalid JSON.');
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}