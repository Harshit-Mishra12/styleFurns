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
            'area' => 'nullable|string|max:255'
        ]);

        $user = Auth::user();

        if ($user->role !== 'technician') {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Unauthorized access. Only technicians can update location.',
            ]);
        }

        // Always insert a new location row
        $area = new \App\Models\TechnicianArea();
        $area->user_id = $user->id;
        $area->latitude = $request->latitude;
        $area->longitude = $request->longitude;
        $area->area = $request->area; // can be null
        $area->save();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'latitude' => $area->latitude,
                'longitude' => $area->longitude,
                'area' => $area->area,
            ],
            'message' => 'Location added successfully.',
        ]);
    }

    public function getProfile(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'technician') {
            return response()->json([
                'status_code' => 2,
                'message' => 'Only technicians can access this profile.',
            ], 403);
        }

        // Get latest location
        $latestLocation = $user->technicianAreas()->latest()->first();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'profile_picture' => $user->profile_picture,
                'job_status' => $user->job_status,
                'joined_at' => $user->created_at->toDateString(),
                'skills' => $user->skills()->pluck('name'),
                'location' => $latestLocation ? [
                    'latitude' => $latestLocation->latitude,
                    'longitude' => $latestLocation->longitude,
                    'area' => $latestLocation->area,
                    'updated_at' => $latestLocation->created_at->toDateTimeString(),
                ] : null
            ],
            'message' => 'Technician profile fetched successfully.'
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'mobile'          => 'sometimes|string|unique:users,mobile,' . $user->id,
            'password'        => 'nullable|string|min:6|confirmed',
            'job_status'      => 'nullable|in:online,offline,engaged',
            'status'          => 'nullable|in:active,inactive',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('profile_picture')) {
            $user->profile_picture = Helper::saveImageToServer($request->file('profile_picture'), 'uploads/profile/');
        }

        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        // Only update allowed fields
        $user->fill($request->only([
            'mobile',
            'job_status',
            'status',
        ]));

        $user->save();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Profile updated successfully.',
            'data'        => [
                'user' => $user->only([
                    'id',
                    'name',
                    'email',
                    'mobile',
                    'profile_picture',
                    'job_status',
                    'status',
                    'role'
                ])
            ]
        ]);
    }
}
