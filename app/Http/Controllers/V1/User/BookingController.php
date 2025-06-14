<?php

namespace App\Http\Controllers\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingImage;
use Illuminate\Http\Request;
use App\Services\TechnicianAssignmentService;



class BookingController extends Controller
{



    public function updateAssignmentStatus(Request $request, $booking_id, TechnicianAssignmentService $assignmentService)
    {
        $request->validate([
            'status'   => 'required|in:rescheduled,rejected,busy,left,completed',
            'reason'   => 'required|in:missing_parts,customer_unavailable,other',
            'comment'  => 'required|string|max:255',
        ]);

        $user = auth()->user();

        $assignment = BookingAssignment::where('booking_id', $booking_id)
            ->where('user_id', $user->id)
            ->where('status', 'assigned')
            ->latest()
            ->first();

        if (!$assignment) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'No active assignment found for this booking.',
            ]);
        }

        $booking = Booking::with('customer')->find($booking_id);
        if (!$booking || !$booking->customer) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Booking or customer not found.',
            ]);
        }

        // ✅ Completed
        if ($request->status === 'completed') {
            $assignment->update([
                'status' => 'completed',
                'reason' => $request->reason,
                'comment' => $request->comment,
                'responded_at' => now(),
            ]);

            $booking->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return response()->json([
                'status_code' => 1,
                'data' => [],
                'message' => 'Booking marked as completed by technician.',
            ]);
        }

        // ✅ Update assignment with reason + comment
        $assignment->update([
            'status' => $request->status,
            'reason' => $request->reason,
            'comment' => $request->comment,
            'responded_at' => now(),
        ]);

        // ✅ Exclude all technicians who failed this booking today
        $excludedTechs = BookingAssignment::where('booking_id', $booking_id)
            ->whereDate('created_at', now()->toDateString())
            ->whereNotIn('status', ['assigned', 'completed'])
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // ✅ Attempt reassignment
        $newTechnician = $assignmentService->assignNearestTechnician($booking, $excludedTechs);

        if ($newTechnician) {
            return response()->json([
                'status_code' => 2,
                'data' => [
                    'reassigned_to' => $newTechnician->id,
                    'technician_name' => $newTechnician->name,
                    'technician_email' => $newTechnician->email,
                ],
                'message' => 'Technician updated status. Booking reassigned to next available technician.',
            ]);
        }

        return response()->json([
            'status_code' => 2,
            'data' => [],
            'message' => 'Status updated, but no technician is available for reassignment today.',
        ]);
    }

    public function uploadBookingImages(Request $request, $booking_id)
    {

        $request->validate([
            'type' => 'required|in:before,after',
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $booking = Booking::find($booking_id);
        if (!$booking) {
            return response()->json([
                'status_code' => 0,
                'data' => [],
                'message' => 'Booking not found.',
            ]);
        }

        foreach ($request->file('images') as $imageFile) {
            $imageUrl = Helper::saveImageToServer($imageFile, 'uploads/bookings/');

            BookingImage::create([
                'booking_id' => $booking->id,
                'image_url' => $imageUrl,
                'type' => $request->type,
                'uploaded_by' => auth()->id(),
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Images uploaded successfully.',
            'data' => []
        ]);
    }
}
