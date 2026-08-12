<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use App\Services\LicenseRepo;

class SetLicenseSeats extends Command
{
    // This defines what you type in the terminal
    protected $signature = 'license:set {count : The number of seats allowed}';

    protected $description = 'Updates the encrypted seat count (LAC) in HS_SYS and clears the cache';

    public function handle(LicenseRepo $repo)
    {
        $count = $this->argument('count');

        // Validation
        if (!is_numeric($count) || $count < 0) {
            $this->error('Error: Please provide a valid positive number.');
            return 1;
        }

        try {
            // 1. Update the Database with the Encrypted value
            // We use Crypt::encryptString because your LicenseRepo expects it
            DB::table('HS_SYS')->where('SYS_KEY', 'LAC')->update([
                'SYS_VALUE' => Crypt::encryptString((string)$count)
            ]);

            // 2. Clear the cache so the change is instant
            $repo->clearCache();

            $this->info("Successfully updated license to {$count} seats.");
            $this->line("- Database record (LAC) updated with encrypted value.");
            $this->line("- Cache key 'seat_cap' has been cleared.");
        } catch (\Exception $e) {
            $this->error("Failed to update license: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
