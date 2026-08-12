<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class TouchLastSeen
{
    public function handle($request, Closure $next)
    {
        if ($user = $request->user()) {
            DB::table('USERS')->where('USER_CODE', $user->id)->update(['last_seen_at' => now()]);
        }
        return $next($request);
    }
}