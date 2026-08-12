<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class AuthHeartbeatController extends Controller
{
    /**
     * Calculates the session lifetime in seconds, reading the value from .env.
     * @return int
     */
    private static function getInactivityLimitSeconds(): int
    {
        // Reads SESSION_LIFETIME (in minutes) and converts to seconds.
        // If SESSION_LIFETIME=1, this returns 60.
        return (int)Config::get('session.lifetime') * 60; 
    }

    /**
     * Resets the activity timestamp in the cache.
     */
    public static function setLastActivity(): void
    {
        $key = self::key();
        
        // Use a lifetime double the session limit for cache to ensure the key
        // stays valid longer than the session check (e.g., 2 minutes if limit is 1 min).
        $cacheLifetimeMinutes = Config::get('session.lifetime') * 2;
        
        Cache::put($key, now()->timestamp, now()->addMinutes($cacheLifetimeMinutes));
    }

    /**
     * Checks if the session has expired due to inactivity.
     * Uses the dynamically calculated limit from the .env file.
     * @return bool
     */
    public static function isExpired(): bool
    {
        $lastActivity = Cache::get(self::key()); 
        
        // If the timestamp is missing (new session), it's NOT expired.
        if ($lastActivity === null) {
            return false;
        }

        // Get the dynamic limit and use it for the expiration check
        $limit = self::getInactivityLimitSeconds();
        
        return (now()->timestamp - (int)$lastActivity) > $limit;
    }

    /**
     * Endpoint to be hit by the client-side heartbeat script (remains the same).
     */
    public function touch(Request $request)
    {
        if (!Auth::check()) return response()->json(['ok' => false], 401);
        self::setLastActivity(); 
        return response()->json(['ok' => true, 'serverNow' => now()->toIso8601String()]);
    }

    /**
     * Generates a unique cache key (remains the same).
     */
    private static function key(): string
    {
        return 'sess:last_activity:' . session()->getId();
    }
}