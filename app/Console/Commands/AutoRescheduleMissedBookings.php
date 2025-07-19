<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\BookingAssignment;
use Carbon\Carbon;
use DB;

class AutoRescheduleMissedBookings extends Command
{
    protected $signature = 'bookings:auto-reschedule';
    protected $description = 'Auto reschedule bookings not started by end of their slot time';
    public function handle()
    {
        // $now = Carbon::now();
        $now = Carbon::now('America/Toronto');

        $this->info("current time: {    $now}");
        //$now = Carbon::parse('2025-06-24  07:28:49');
        // $now = Carbon::parse('2025-06-24 12:50:00');
        $today = $now->toDateString();

        $affected = 0;

        DB::transaction(function () use ($today, $now, &$affected) {
            $assignments = BookingAssignment::whereDate('slot_date', $today)
                ->whereNull('started_at')
                ->where('status', 'assigned')
                ->whereRaw("STR_TO_DATE(CONCAT(slot_date, ' ', time_end), '%Y-%m-%d %H:%i:%s') < ?", [$now])
                ->whereHas('booking', function ($q) {
                    $q->where('status', 'pending');
                })
                ->get();

            $this->info("✅checking: {  $assignments}");

            foreach ($assignments as $assignment) {
                $booking = $assignment->booking;

                if (!$booking) {
                    continue;
                }
                $technicianId = $booking->current_technician_id ?: $assignment->user_id;
                $assignment->update([
                    'status' => 'unassigned',
                    'reason' => 'arrived_late',
                ]);

                $booking->update([
                    'status' => 'rescheduling_required',
                    'reason' => 'arrived_late',
                    'current_technician_id' => null,
                ]);

                $affected++;

                if ($technicianId) {
                    Helper::sendPushNotification(3, [$technicianId]);
                }
            }
        });

        $this->info("✅ Rescheduling completed. Bookings affected: {$affected}");
    }
    // public function handle()
    // {
    //     $now = Carbon::now();
    //     $this->info("check.");
    //     // Fetch all pending bookings whose time_end has passed, and not started
    //     $bookings = Booking::with('bookingAssignment')
    //         ->where('status', 'pending')
    //         ->whereDate('scheduled_date', $now->toDateString())
    //         ->whereTime('time_end', '<=', $now->format('H:i:s'))
    //         ->get();

    //     $rescheduledCount = 0;

    //     foreach ($bookings as $booking) {
    //         $assignment = $booking->bookingAssignment;

    //         // If no assignment or not started
    //         if (!$assignment || !$assignment->started_at) {
    //             // Update booking
    //             $booking->update([
    //                 'status' => 'rescheduling',
    //                 'current_technician_id' => null,
    //                 'reason' => 'arrived_late',
    //             ]);

    //             // Update assignment
    //             if ($assignment) {
    //                 $assignment->update([
    //                     'status' => 'unassigned',
    //                     'started_at' => null,
    //                     'ended_at' => null,
    //                     'current_job_status' => null,
    //                 ]);
    //             }

    //             $rescheduledCount++;
    //         }
    //     }

    //     $this->info("{$rescheduledCount} booking(s) moved to rescheduling due to missed slot.");
    //     return 0;
    // }
}
