<?php

namespace App\Http\Controllers;

use App\Services\LicenseRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenseManagementController extends Controller
{
    public function status(LicenseRepo $repo)
    {
        try {
            $cap = $repo->getSeatCap();

            $activeUsers = DB::connection('tenant')
                ->table('USERS')
                ->where('LOGIN_STAT', 1)
                ->whereNotIn('USER_CODE', ['HEARTSTRONG', 'MIRACLE'])
                ->select('USER_CODE', 'USER_NAME', 'LAST_SEEN_AT', 'LAST_LOGIN_AT')
                ->orderBy('USER_CODE')
                ->get();

            $activeCount = $activeUsers->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'seatCap' => (int) $cap,
                    'activeSeats' => $activeCount,
                    'remainingSeats' => max(0, (int) $cap - $activeCount),
                    'activeUsers' => $activeUsers,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('License status failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve license status.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSeats(Request $request, LicenseRepo $repo)
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:0'],
        ]);

        $count = (int) $validated['count'];

        try {
            $updated = DB::connection('tenant')
                ->table('HS_SYS')
                ->where('SYS_KEY', 'LAC')
                ->update([
                    'SYS_VALUE' => Crypt::encryptString((string) $count),
                ]);

            if ($updated === 0) {
                $exists = DB::connection('tenant')
                    ->table('HS_SYS')
                    ->where('SYS_KEY', 'LAC')
                    ->exists();

                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The LAC license setting was not found in HS_SYS.',
                    ], 404);
                }
            }

            $repo->clearCache();

            return response()->json([
                'success' => true,
                'message' => "License seat capacity updated to {$count}.",
                'data' => [
                    'seatCap' => $count,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('License seat update failed', [
                'count' => $count,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update license seats.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
