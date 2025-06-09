<?php

namespace App\Http\Controllers\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\TechnicianArea;
use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Services\BookingPartsService;
use App\Services\TechnicianAssignmentService;


// Functionality	Method Name
// Toggle Online/Offline	updateJobStatus(Request $request)
// Update live location (lat/lng)	updateLocation(Request $request)
// View current status & availability	show()
// Update profile details (name, phone, etc.)	update(Request $request)
// View technician dashboard summary	dashboard()
class TechnicianProfileController extends Controller
{


    public function updateJobStatus(Request $request)
    {

        $request->validate([
            'job_status' => 'required|in:online,offline,engaged',
        ]);

        $user = Auth::user();

        if ($user->role !== 'technician') {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Unauthorized access. Only technicians can perform this action.',
            ]);
        }

        $user->job_status = $request->job_status;
        $user->save();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'job_status' => $user->job_status
            ],
            'message' => 'Job status updated successfully.',
        ]);
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'area' => 'required'
        ]);

        $user = Auth::user();

        if ($user->role !== 'technician') {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Unauthorized access. Only technicians can update location.',
            ]);
        }

        $area = \App\Models\TechnicianArea::firstOrNew(['user_id' => $user->id]);
        $area->latitude = $request->latitude;
        $area->longitude = $request->longitude;
        $area->area = $request->area;
        $area->save();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'latitude' => $area->latitude,
                'longitude' => $area->longitude,
                'area' => $area->area
            ],
            'message' => 'Location updated successfully.',
        ]);
    }
}
