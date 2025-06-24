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

        $uploadedImageIds = [];

        foreach ($request->file('images') as $imageFile) {
            $imageUrl = Helper::saveImageToServer($imageFile, 'uploads/bookings/');

            $bookingImage = BookingImage::create([
                'booking_id' => $booking->id,
                'image_url' => $imageUrl,
                'type' => $request->type,
                'uploaded_by' => auth()->id(),
            ]);

            $uploadedImageIds[] = $bookingImage->id;
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Images uploaded successfully.',
            'data' => [
                'uploaded_image_ids' => $uploadedImageIds
            ]
        ]);
    }

    public function deleteBookingImage(Request $request, $booking_id)
    {
        $request->validate([
            'imageId' => 'required|integer',
            'type' => 'required|in:before,after',
        ]);

        $booking = Booking::find($booking_id);

        if (!$booking) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Booking not found.',
            ]);
        }

        $bookingImage = BookingImage::where('id', $request->imageId)
            ->where('booking_id', $booking_id)
            ->where('type', $request->type)
            ->first();

        if (!$bookingImage) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Booking image not found.',
            ]);
        }

        // Optional: Delete the physical file from server
        if (file_exists(public_path($bookingImage->image_url))) {
            unlink(public_path($bookingImage->image_url));
        }

        $bookingImage->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Booking image deleted successfully.',
        ]);
    }



    public function updateJobStatus(Request $request, $id)
    {
        $request->validate([
            'current_job_status' => 'required|string', //in:enroute,on-site,working,completed
            'started_at'         => 'nullable|date',
            'ended_at'           => 'nullable|date|after_or_equal:started_at',
        ]);

        $assignment = BookingAssignment::findOrFail($id);

        $assignment->current_job_status = $request->current_job_status;

        if ($request->filled('started_at')) {
            $assignment->started_at = $request->started_at;
        }

        if ($request->filled('ended_at')) {
            $assignment->ended_at = $request->ended_at;
        }

        $assignment->save();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Job status updated successfully.',
            'data'        => $assignment,
        ]);
    }
}
