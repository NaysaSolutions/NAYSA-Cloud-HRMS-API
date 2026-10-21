<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class LicenseRepo
{
    private const CACHE_SECONDS = 300;

    /**
     * Return the active tenant database connection.
     */
    private function tenantConnection(): Connection
    {
        return DB::connection('tenant');
    }

    /**
     * Build an identity using the active tenant server and database.
     */
    private function tenantIdentity(): string
    {
        $connection = $this->tenantConnection();

        $tenantConfig = config(
            'database.connections.tenant',
            []
        );

        $host = strtolower(trim(
            (string) ($tenantConfig['host'] ?? '')
        ));

        $port = trim(
            (string) ($tenantConfig['port'] ?? '1433')
        );

        $database = strtolower(trim(
            (string) $connection->getDatabaseName()
        ));

        if ($database === '') {
            throw new RuntimeException(
                'The active tenant database could not be identified.'
            );
        }

        return "{$host}:{$port}/{$database}";
    }

    /**
     * Each tenant uses a separate cache key.
     */
    private function seatCapCacheKey(): string
    {
        return 'license:seat_cap:' . hash(
            'sha256',
            $this->tenantIdentity()
        );
    }

    public function getSeatCap(): int
    {
        $cacheKey = $this->seatCapCacheKey();

        return (int) Cache::remember(
            $cacheKey,
            self::CACHE_SECONDS,
            function (): int {
                $row = $this->tenantConnection()
                    ->table('HS_SYS')
                    ->where('SYS_KEY', 'LAC')
                    ->selectRaw(
                        'CAST(SYS_VALUE AS NVARCHAR(MAX)) AS sys_value'
                    )
                    ->first();

                if (
                    !$row ||
                    $row->sys_value === null ||
                    trim((string) $row->sys_value) === ''
                ) {
                    throw new RuntimeException(
                        'HS_SYS LAC was not found or SYS_VALUE is empty.'
                    );
                }

                $rawValue = trim((string) $row->sys_value);

                try {
                    $plainValue = Crypt::decryptString($rawValue);
                } catch (Throwable $exception) {
                    if (preg_match('/^\d+$/', $rawValue)) {
                        $plainValue = $rawValue;
                    } else {
                        throw new RuntimeException(
                            'The tenant license value cannot be decrypted '
                            . 'and is not a plain numeric value.',
                            0,
                            $exception
                        );
                    }
                }

                return max(0, (int) $plainValue);
            }
        );
    }

    /**
     * Clear only the active tenant's cached seat capacity.
     *
     * Also remove the old global cache key once so it can no longer
     * affect other tenants.
     */
    public function clearCache(): void
    {
        Cache::forget($this->seatCapCacheKey());
        Cache::forget('seat_cap');
    }

    public function getTenantIdentity(): string
    {
        return $this->tenantIdentity();
    }

    public function getCacheKey(): string
    {
        return $this->seatCapCacheKey();
    }
}