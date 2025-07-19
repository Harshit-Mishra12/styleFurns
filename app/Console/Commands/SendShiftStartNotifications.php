<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Helpers\Helper;

class SendShiftStartNotifications extends Command
{
    protected $signature = 'notifications:shift-start';
    protected $description = 'Send shift start reminders to all active and offline technicians';

    public function handle()
    {
        // Fetch all active and offline technicians
        $technicians = User::where('status', 'active')
            ->where('job_status', 'offline')
            ->pluck('id');

        if ($technicians->isEmpty()) {
            $this->info('No offline technicians to notify.');
            return 0;
        }

        // Send Shift Starting Soon notification (type 5)
        Helper::sendPushNotification(5, $technicians->toArray());
        $this->info('Shift start notifications sent to technicians: ' . implode(',', $technicians->toArray()));

        return 0;
    }
}
