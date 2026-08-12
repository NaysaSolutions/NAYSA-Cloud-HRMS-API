<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class TenantCatalog
{
    protected string $jsonPath;
    protected int $ttl;

    public function __construct(?string $jsonPath = null, ?int $ttl = null)
    {
        $this->jsonPath = $jsonPath ?: storage_path('app/tenants.json');
        $this->ttl = $ttl ?: (int) env('TENANT_CACHE_SECONDS', 300);
    }

    public function all(): array
    {
        $key = "tenant_json_catalog_" . md5($this->jsonPath . '|' . (file_exists($this->jsonPath) ? filemtime($this->jsonPath) : ''));

        return Cache::remember($key, $this->ttl, function () {
            if (!file_exists($this->jsonPath)) {
                return [];
            }

            $contents = file_get_contents($this->jsonPath);
            $data = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            // normalize keys to lowercase for consistency
            return array_map(function ($row) {
                return array_change_key_case($row, CASE_LOWER);
            }, $data);
        });
    }

    public function find(string $needle): ?array
    {
        $needle = trim($needle);

        foreach ($this->all() as $row) {
            if (strcasecmp($row['code'] ?? '', $needle) === 0) {
                return $row;
            }
            if (strcasecmp($row['database'] ?? '', $needle) === 0) {
                return $row;
            }
        }

        return null;
    }
}
