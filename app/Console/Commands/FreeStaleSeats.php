<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FreeStaleSeats extends Command
{
    protected $signature = 'seats:free-stale {--grace=2}';
    protected $description = 'Free seats for users idle beyond session lifetime (+grace)';

    public function handle()
    {
        $sessionMinutes = (int) config('session.lifetime', 60); // from .env
        $graceMinutes   = (int) $this->option('grace');          // small buffer
        $cutoff         = now()->subMinutes($sessionMinutes + $graceMinutes);

        $n = DB::table('USERS')
            ->where('LOGIN_STAT', 1)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_seen_at')
                  ->orWhere('last_seen_at', '<', $cutoff);
            })
            ->update(['LOGIN_STAT' => 0]);

        $this->info("Freed {$n} stale seats (idle > {$sessionMinutes}+{$graceMinutes} mins).");
        return self::SUCCESS;
    }
}