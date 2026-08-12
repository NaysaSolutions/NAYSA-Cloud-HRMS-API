<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RMInvBalanceController extends Controller
{
    public function getInvLookup(Request $request)
    {
        try {
            $jsonString = $request->input('PARAMS');

            if (!$jsonString) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing PARAMS',
                ], 400);
            }

            $results = DB::select(
                'EXEC sproc_PHP_INVLookup_RM @mode = ?, @params = ?',
                ['Get', $jsonString]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}