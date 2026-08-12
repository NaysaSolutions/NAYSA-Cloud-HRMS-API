<?php
namespace App\Services;

use Illuminate\Support\Facades\{Cache, DB, Crypt};

class LicenseRepo
{
    public function getSeatCap(): int
    {
        return Cache::remember('seat_cap', 300, function () {
            // Force a lowercase alias so stdClass has ->sys_value
            $row = DB::table('HS_SYS')
                ->where('SYS_KEY', 'LAC')
                ->selectRaw('CAST(SYS_VALUE AS NVARCHAR(MAX)) AS sys_value')
                ->first();

            if (!$row || $row->sys_value === null) {
                throw new \RuntimeException('HS_SYS LAC not found or has null SYS_VALUE');
            }

            $raw = (string) $row->sys_value;

            // If you stored encrypted (recommended):
            try {
                $plain = Crypt::decryptString($raw);
            } catch (\Throwable $e) {
                // Fallback: accept plain numeric if not encrypted
                if (preg_match('/^\d+$/', trim($raw))) {
                    $plain = trim($raw);
                } else {
                    throw new \RuntimeException('License value cannot be decrypted and is not numeric');
                }
            }

            return max(0, (int) $plain);
        });
    }

    public function clearCache(): void
    {
        Cache::forget('seat_cap');
    }
}
