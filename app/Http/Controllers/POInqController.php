<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class POInqController extends Controller
{
    public function getPOInquiry(Request $request)
    {
        try {
            $jsonData = $request->query('json_data', []);

            if (is_string($jsonData)) {
                $jsonData = json_decode($jsonData, true) ?? [];
            }

            if (!is_array($jsonData)) {
                $jsonData = [];
            }

            $params = [
                'json_data' => [
                    'branchCode'     => $jsonData['branchCode'] ?? '',
                    'itemCode'       => $jsonData['itemCode'] ?? $jsonData['itemNo'] ?? '',
                    'poStatus'       => $jsonData['poStatus'] ?? $jsonData['poStat'] ?? '',
                    'startingDate'   => $jsonData['startingDate'] ?? '',
                    'endingDate'     => $jsonData['endingDate'] ?? '',
                    'startingCutoff' => $jsonData['startingCutoff'] ?? '',
                    'endingCutoff'   => $jsonData['endingCutoff'] ?? '',
                    'rcCode'         => $jsonData['rcCode'] ?? $jsonData['actCode'] ?? '',
                    'vendCode'       => $jsonData['vendCode'] ?? '',
                    'invType'        => $jsonData['invType'] ?? '',
                ],
            ];

            $results = DB::select(
                'EXEC dbo.sproc_PHP_PO_Inq @_params = ?',
                [json_encode($params)]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
