<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Helpers\Helper;

class SendShiftEndNotifications extends Command
{
    protected $signature = 'notifications:shift-end';
    protected $description = 'Send shift end reminders to all active and online technicians';

    public function handle()
    {
        // Fetch all active and online technicians
        $technicians = User::where('status', 'active')
            ->where('job_status', 'online')
            ->pluck('id');

        if ($technicians->isEmpty()) {
            $this->info('No online technicians to notify.');
            return 0;
        }

        // Send Shift Ending Soon notification (type 6)
        Helper::sendPushNotification(6, $technicians->toArray());
        $this->info('Shift end notifications sent to technicians: ' . implode(',', $technicians->toArray()));

        return 0;
    }
}
