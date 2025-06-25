<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingImage;
use App\Models\BookingPart;
use App\Models\Customer;
use App\Services\BookingPartsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\TechnicianAssignmentService;



class BookingPartsController extends Controller
{


    public function addMissingParts(Request $request, $booking_id, BookingPartsService $partsService)
    {


        $request->validate([
            'parts' => 'required|array|min:1',
            'parts.*.name' => 'required|string',
            'parts.*.serial_number' => 'nullable|string',
            'parts.*.quantity' => 'nullable|numeric|min:0.01',
            'parts.*.unit_type' => 'nullable|in:unit,gram,kg,ml,liter,meter',
            'parts.*.provided_by' => 'nullable|in:admin,technician,customer,unknown',
            'parts.*.notes' => 'nullable|string|max:500',
        ]);

        $booking = Booking::find($booking_id);
        if (!$booking) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Booking not found.',
            ]);
        }

        $insertedIds = $partsService->addMissingParts($request->parts, $booking, auth()->id());

        return response()->json([
            'status_code' => 1,
            'message' => 'Missing parts recorded successfully.',
            'data' => [
                'part_ids' => $insertedIds
            ],
        ]);
    }
    public function deleteBookingPart(Request $request, $booking_id)
    {
        $request->validate([
            'partId' => 'required|integer|exists:booking_parts,id',
        ]);

        $booking = Booking::find($booking_id);
        if (!$booking) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Booking not found.',
            ]);
        }

        $part = BookingPart::where('id', $request->partId)
            ->where('booking_id', $booking_id)
            ->first();

        if (!$part) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Part not found or does not belong to this booking.',
            ]);
        }

        $part->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Part deleted successfully.',
            'data' => [
                'deleted_part_id' => $request->partId
            ]
        ]);
    }


    // public function addMissingParts(Request $request, $booking_id, BookingPartsService $partsService)
    // {
    //     $request->validate([
    //         'parts' => 'required|array|min:1',
    //         'parts.*.name' => 'required|string',
    //         'parts.*.serial_number' => 'nullable|string',
    //         'parts.*.quantity' => 'nullable|numeric|min:0.01',
    //         'parts.*.unit_type' => 'nullable|in:unit,gram,kg,ml,liter,meter',
    //         'parts.*.provided_by' => 'nullable|in:admin,technician,customer,unknown',
    //         'parts.*.notes' => 'nullable|string|max:500',
    //     ]);

    //     $booking = Booking::find($booking_id);
    //     if (!$booking) {
    //         return response()->json([
    //             'status_code' => 2,
    //             'data' => [],
    //             'message' => 'Booking not found.',
    //         ]);
    //     }

    //     $partsService->addMissingParts($request->parts, $booking, auth()->id());

    //     return response()->json([
    //         'status_code' => 1,
    //         'message' => 'Missing parts recorded successfully.',
    //         'data' => [],
    //     ]);
    // }
    public function update(Request $request, $booking_id, $part_id)
    {
        $request->validate([
            'part_name'     => 'nullable|string',
            'serial_number' => 'nullable|string',
            'quantity'      => 'nullable|numeric|min:0.01',
            'unit_type'     => 'nullable|in:unit,gram,kg,ml,liter,meter',
            'price'         => 'nullable|numeric|min:0',
            'provided_by'   => 'nullable|in:admin,technician,customer,unknown',
            'is_provided'   => 'nullable|boolean',
            'is_required'   => 'nullable|boolean',
            'notes'         => 'nullable|string|max:500',
        ]);

        $part = BookingPart::where('booking_id', $booking_id)->where('id', $part_id)->first();

        if (!$part) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Part not found for this booking.',
                'data' => [],
            ]);
        }

        $part->update($request->only([
            'part_name',
            'serial_number',
            'quantity',
            'unit_type',
            'price',
            'provided_by',
            'is_provided',
            'is_required',
            'notes'
        ]));

        return response()->json([
            'status_code' => 1,
            'message' => 'Booking part updated successfully.',
            'data' => $part,
        ]);
    }
    public function index($booking_id)
    {
        $parts = BookingPart::where('booking_id', $booking_id)->get();

        if ($parts->isEmpty()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'No parts found for this booking.',
                'data' => [],
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Parts fetched successfully.',
            'data' => $parts,
        ]);
    }
}
