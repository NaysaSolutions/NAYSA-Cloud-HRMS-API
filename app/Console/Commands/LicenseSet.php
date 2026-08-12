<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class LicenseSet extends Command
{
    protected $signature = 'license:set {count}';
    protected $description = 'Set concurrent seat count into HS_SYS (encrypted)';

    public function handle()
    {
        $count = (int) $this->argument('count');
        DB::table('HS_SYS')->updateOrInsert(
            ['sys_key' => 'LAC'],
            ['sys_value' => Crypt::encryptString((string)$count)]
        );
        $this->info("License set to {$count} seats.");
        return self::SUCCESS;

        // php artisan license:set 5
    }
}