<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;

class SendLocationUpdateReminders extends Command
{
    protected $signature = 'notifications:location-update';
    protected $description = 'Send location update reminders to active & online technicians whose last location update is >= 15 minutes old.';

    public function handle()
    {
        $threshold = now()->subMinutes(15);

        /**
         * Build a subquery that gets the latest (max) updated_at for each technician in technician_areas.
         */
        $latestAreas = DB::table('technician_areas')
            ->select('user_id', DB::raw('MAX(updated_at) as last_update'))
            ->groupBy('user_id');

        /**
         * Join users (active + online + role technician) to their latest area record.
         * Include users with NO area row yet (treated as stale) via LEFT JOIN.
         */
        $staleUserIds = DB::table('users')
            ->leftJoinSub($latestAreas, 'ta', function ($join) {
                $join->on('ta.user_id', '=', 'users.id');
            })
            ->where('users.role', 'technician')
            ->where('users.status', 'active')        // active
            ->where('users.job_status', 'online')    // currently online
            ->where(function ($q) use ($threshold) {
                $q->whereNull('ta.last_update')      // never updated => stale
                    ->orWhere('ta.last_update', '<=', $threshold);
            })
            ->pluck('users.id');

        if ($staleUserIds->isEmpty()) {
            $this->info('No stale location technicians found.');
            return 0;
        }

        // Send notification type 4 (Location Update Needed)
        Helper::sendPushNotification(4, $staleUserIds->toArray());

        $this->info('Location update reminders sent to technician IDs: ' . $staleUserIds->implode(','));
        return 0;
    }
}
