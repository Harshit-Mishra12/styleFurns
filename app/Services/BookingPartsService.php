<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPart;

class BookingPartsService
{

    public function addMissingParts(array $parts, Booking $booking, int $userId): array
    {
        $insertedIds = [];

        foreach ($parts as $part) {
            $bookingPart = BookingPart::create([
                'booking_id'    => $booking->id,
                'part_name'     => $part['name'],
                'serial_number' => $part['serial_number'] ?? null,
                'quantity'      => $part['quantity'] ?? 1,
                'unit_type'     => $part['unit_type'] ?? 'unit',
                'provided_by'   => $part['provided_by'] ?? 'unknown',
                'is_required'   => true,
                'is_provided'   => false,
                'added_by'      => $userId,
                'added_source'  => 'technician',
                'notes'         => $part['notes'] ?? null,
                'price'         => $part['price'] ?? null,
            ]);

            $insertedIds[] = $bookingPart->id;
        }

        return $insertedIds;
    }

    // public function addMissingParts(array $parts, Booking $booking, int $userId): void
    // {
    //     foreach ($parts as $part) {
    //         BookingPart::create([
    //             'booking_id'    => $booking->id,
    //             'part_name'     => $part['name'],
    //             'serial_number' => $part['serial_number'] ?? null,
    //             'quantity'      => $part['quantity'] ?? 1,
    //             'unit_type'     => $part['unit_type'] ?? 'unit',
    //             'provided_by'   => $part['provided_by'] ?? 'unknown',
    //             'is_required'   => true,
    //             'is_provided'   => false,
    //             'added_by'      => $userId,
    //             'added_source'  => 'technician',
    //             'notes'         => $part['notes'] ?? null,
    //             'price' => $part['price'] ?? null
    //         ]);
    //     }
    // }
}
