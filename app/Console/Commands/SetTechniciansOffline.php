<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class SetTechniciansOffline extends Command
{
    protected $signature = 'technicians:set-offline';
    protected $description = 'Automatically sets all technician job_status to offline after 10 PM';

    public function handle()
    {
        $now = Carbon::now();

        // Run only after 10 PM
        if ($now->hour >= 22) {
            $updated = User::where('role', 'technician')
                ->where('job_status', '!=', 'offline')
                ->update(['job_status' => 'offline']);

            $this->info("{$updated} technicians set to offline.");
        } else {
            $this->info("Current time is before 10 PM. No changes made.");
        }

        return 0;
    }
}
