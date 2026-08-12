<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\LicenseRepo;

class GetLicenseStatus extends Command
{
    // The name of the command
    protected $signature = 'license:status';

    protected $description = 'Show current seat usage versus the allowed cap';

    public function handle(LicenseRepo $repo)
    {
        // 1. Get the Cap (uses your existing logic)
        try {
            $cap = $repo->getSeatCap();
        } catch (\Exception $e) {
            $this->error("Could not retrieve license cap: " . $e->getMessage());
            return 1;
        }

        // 2. Count active users (LOGIN_STAT = 1)
        $activeCount = DB::table('USERS')->where('LOGIN_STAT', 1)->count();
        $remaining = max(0, $cap - $activeCount);

        // 3. Display a nice table
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Seats (Cap)', $cap],
                ['Active Sessions', $activeCount],
                ['Seats Remaining', $remaining],
            ]
        );

        if ($activeCount >= $cap && $cap > 0) {
            $this->warn("!!! ALERT: License is FULL. New logins will be denied.");
        }

        // 4. Show who is currently logged in
        if ($activeCount > 0) {
            $this->newLine();
            $this->info("Currently Active Users:");
            $users = DB::table('USERS')
                ->where('LOGIN_STAT', 1)
                ->select('USER_CODE', 'LAST_SEEN_AT')
                ->get();

            foreach ($users as $user) {
                $this->line("- {$user->USER_CODE} (Active since: {$user->LAST_SEEN_AT})");
            }
        }

        return 0;
    }
}