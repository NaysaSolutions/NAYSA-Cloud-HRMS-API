<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SeatGate
{
    public function __construct(private LicenseRepo $license) {}

    /**
     * Attempt to occupy a seat for a given USER_CODE.
     * Returns true if login is allowed, false if license cap exceeded.
     */
    public function tryOccupy(string $userCode): bool
    {
        return DB::transaction(function () use ($userCode) {
            // Prevent race condition: only one seat check at a time
            DB::statement("EXEC sp_getapplock @Resource='seat_lock', @LockMode='Exclusive', @LockTimeout=5000");

            // If user already active, just refresh last_seen_at
            $already = (int) DB::table('USERS')->where('USER_CODE', $userCode)->value('LOGIN_STAT');
            if ($already === 1) {
                DB::table('USERS')->where('USER_CODE', $userCode)->update(['LAST_SEEN_AT' => now()]);
                return true;
            }

            // Retrieve allowed concurrent seat count
            $cap = $this->license->getSeatCap();

            // Count how many users are currently active
            $active = (int) DB::table('USERS')->where('LOGIN_STAT', 1)->count();

            // Deny if no license or already at max
            if ($cap === 0 || $active >= $cap) {
                return false;
            }

            // Occupy seat
            DB::table('USERS')->where('USER_CODE', $userCode)->update([
                'LOGIN_STAT' => 1,
                'LAST_SEEN_AT' => now(),
            ]);

            return true;
        });
    }

    /**
     * Release seat when user logs out.
     */
    public function release(string $userCode): void
    {
        DB::table('USERS')->where('USER_CODE', $userCode)->update([
            'LOGIN_STAT' => 0,
            'LAST_SEEN_AT' => now(),
        ]);
    }
}
