<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthHeartbeatController;
use Illuminate\Support\Facades\Auth;

class EnsureRecentActivity
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            
            // 1. Check for expiration
            if (AuthHeartbeatController::isExpired()) {
                
                // Perform logout actions
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                // Return 401 to trigger the client-side "Session ended" message
                return response()->json(['message' => 'Session expired due to inactivity'], 401);
            }

            // 2. If NOT expired, update the timestamp for the current request
            AuthHeartbeatController::setLastActivity();
        }

        return $next($request);
    }
}