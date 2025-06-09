<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\BookingAssignment;

class TechnicianAssignmentService
{
    public function assignNearestTechnician(Booking $booking, array $excludeTechnicianIds = []): ?User
    {
        $customer = $booking->customer;

        if (!$customer || !$customer->latitude || !$customer->longitude || !$customer->area) {
            return null;
        }

        $technicians = User::where('role', 'technician')
            ->where('job_status', 'online')
            ->whereNotIn('id', $excludeTechnicianIds) // ⛔ Exclude these
            ->whereHas('technicianArea', function ($q) use ($customer) {
                $q->where('area', $customer->area);
            })
            ->with('technicianArea')
            ->get()
            ->filter(function ($tech) {
                return $tech->technicianArea &&
                    $tech->technicianArea->latitude &&
                    $tech->technicianArea->longitude;
            });

        if ($technicians->isEmpty()) {
            return null;
        }

        $nearest = $technicians->sortBy(function ($tech) use ($customer) {
            return sqrt(
                pow($tech->technicianArea->latitude - $customer->latitude, 2) +
                    pow($tech->technicianArea->longitude - $customer->longitude, 2)
            );
        })->first();

        BookingAssignment::create([
            'booking_id' => $booking->id,
            'user_id' => $nearest->id,
            'status' => 'assigned',
            'assigned_at' => now()
        ]);

        $booking->update([
            'current_technician_id' => $nearest->id
        ]);

        return $nearest;
    }

    // public function assignNearestTechnician(Booking $booking): ?User
    // {
    //     $customer = $booking->customer;

    //     if (!$customer || !$customer->latitude || !$customer->longitude || !$customer->area) {
    //         return null;
    //     }

    //     // Get online technicians in same area with lat/lng
    //     $technicians = User::where('role', 'technician')
    //         ->where('job_status', 'online')
    //         ->whereHas('technicianArea', function ($q) use ($customer) {
    //             $q->where('area', $customer->area);
    //         })
    //         ->with('technicianArea')
    //         ->get()
    //         ->filter(function ($tech) {
    //             return $tech->technicianArea &&
    //                 $tech->technicianArea->latitude &&
    //                 $tech->technicianArea->longitude;
    //         });

    //     if ($technicians->isEmpty()) {
    //         return null;
    //     }

    //     // Find nearest technician by distance
    //     $nearest = $technicians->sortBy(function ($tech) use ($customer) {
    //         return sqrt(
    //             pow($tech->technicianArea->latitude - $customer->latitude, 2) +
    //                 pow($tech->technicianArea->longitude - $customer->longitude, 2)
    //         );
    //     })->first();

    //     // Assign technician to booking
    //     BookingAssignment::create([
    //         'booking_id' => $booking->id,
    //         'user_id' => $nearest->id,
    //         'status' => 'assigned',
    //         'assigned_at' => now()
    //     ]);

    //     $booking->update([
    //         'current_technician_id' => $nearest->id
    //     ]);

    //     return $nearest;
    // }
}
