<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Determine Tenant Identity from Header or Query
        $headerName = env('TENANT_HEADER', 'X-Company-DB');
        $raw = $request->header($headerName) 
            ?? $request->query('company') 
            ?? $request->input('company');

        if (!$raw) {
            return response()->json(['message' => "Missing {$headerName} header"], 400);
        }

        // 2. Optimized JSON Loading via Cache
        // This avoids reading the disk on every single API call
        $tenants = Cache::rememberForever('tenant_configurations', function () {
            $path = base_path(env('TENANT_JSON', 'storage/app/tenants.json'));
            if (!is_file($path)) return [];
            return json_decode(file_get_contents($path), true) ?? [];
        });

        if (empty($tenants)) {
            return response()->json(['message' => "Tenants configuration not found"], 500);
        }

        // 3. Find the matching tenant
        $tenant = collect($tenants)->first(function ($t) use ($raw) {
            return isset($t['code'], $t['database']) &&
                   (strcasecmp($t['code'], $raw) === 0 || strcasecmp($t['database'], $raw) === 0);
        });

        if (!$tenant) {
            return response()->json(['message' => "Invalid tenant: {$raw}"], 404);
        }

        // 4. Inject Dynamic Configuration
        Config::set("database.connections.tenant", [
            'driver'   => 'sqlsrv',
            'host'     => $tenant['host']     ?? env('DB_HOST'),
            'port'     => $tenant['port']     ?? env('DB_PORT', '1433'),
            'database' => $tenant['database'],
            'username' => $tenant['username'] ?? env('DB_USERNAME'),
            'password' => $tenant['password'] ?? env('DB_PASSWORD'),
            'charset'  => 'utf8',
            'prefix'   => '',
            'encrypt'  => env('DB_ENCRYPT', 'no'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', true),
        ]);

        // 5. Switch Connection without "getPdo()" (Lazy Loading)
        // This removes the 200ms - 500ms delay per request
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        // 6. Pass metadata to the request for easy access in Controllers
        $request->attributes->set('tenant.database', $tenant['database']);
        $request->attributes->set('tenant.code', $tenant['code'] ?? null);
        $request->attributes->set('tenant.company', $tenant['company'] ?? null);

        return $next($request);
    }
}