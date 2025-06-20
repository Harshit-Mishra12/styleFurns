<?php

namespace App\Http\Controllers\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;


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
        $authUser = Auth::user();
        $technicianId = $request->input('technician_id');

        if ($technicianId) {
            if ($authUser->role !== 'admin') {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Unauthorized to access other technician profiles.',
                ]);
            }

            $user = User::where('id', $technicianId)->where('role', 'technician')->first();

            if (!$user) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Technician not found.',
                ]);
            }
        } else {
            $user = $authUser;

            if ($user->role !== 'technician') {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Only technicians can access this profile.',
                ]);
            }
        }

        // 👇 Manual skill fetch (no relationship)
        $skills = DB::table('technician_skills')
            ->join('skills', 'technician_skills.skill_id', '=', 'skills.id')
            ->where('technician_skills.user_id', $user->id)
            ->select('technician_skills.id as technician_skill_id', 'skills.id as skill_id', 'skills.name as skill_name')
            ->get();

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
                'technician_skills' => $skills,
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
        $authUser = auth()->user();
        $technicianId = $request->input('technician_id');

        // Determine target technician
        if ($technicianId) {
            if ($authUser->role !== 'admin') {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Unauthorized: Only admins can update other technicians.',
                ]);
            }

            $user = User::where('id', $technicianId)->where('role', 'technician')->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Technician not found.',
                ]);
            }
        } else {
            if ($authUser->role !== 'technician') {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Only technicians can update their own profile.',
                ]);
            }

            $user = $authUser;
        }

        // Validation
        $request->validate([
            'mobile'             => 'sometimes|string|unique:users,mobile,' . $user->id,
            'password'           => 'nullable|string|min:6|confirmed',
            'job_status'         => 'nullable|in:online,offline,engaged',
            'status'             => 'nullable|in:active,inactive',
            'profile_picture'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'name'               => 'nullable|string|max:255',
            'technician_skills'  => 'nullable|array',
            'technician_skills.*.skill_id' => 'required|exists:skills,id',
        ]);

        // Profile picture
        if ($request->hasFile('profile_picture')) {
            $user->profile_picture = Helper::saveImageToServer(
                $request->file('profile_picture'),
                'uploads/profile/'
            );
        }

        // Password
        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        // Update basic profile fields
        $user->fill($request->only([
            'name',
            'mobile',
            'job_status',
            'status',
        ]));

        $user->save();

        // 🔄 Update technician_skills
        if ($request->has('technician_skills')) {
            DB::table('technician_skills')->where('user_id', $user->id)->delete();

            $skillsToInsert = [];
            foreach ($request->technician_skills as $skill) {
                $skillsToInsert[] = [
                    'user_id'    => $user->id,
                    'skill_id'   => $skill['skill_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($skillsToInsert)) {
                DB::table('technician_skills')->insert($skillsToInsert);
            }
        }

        // 📦 Fetch technician_skills with skill names
        $technicianSkills = DB::table('technician_skills')
            ->join('skills', 'technician_skills.skill_id', '=', 'skills.id')
            ->where('technician_skills.user_id', $user->id)
            ->select(
                'technician_skills.id as technician_skill_id',
                'skills.id as skill_id',
                'skills.name as skill_name'
            )
            ->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Technician profile updated successfully.',
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
                ]),
                'technician_skills' => $technicianSkills
            ]
        ]);
    }


    public function getJourney(Request $request)
    {
        $authUser = Auth::user();
        $technicianId = $request->input('technician_id');

        if ($technicianId) {
            if ($authUser->role !== 'admin') {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Unauthorized access.',
                ]);
            }

            $user = User::where('id', $technicianId)->where('role', 'technician')->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Technician not found.',
                ]);
            }
        } else {
            if ($authUser->role !== 'technician') {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Only technicians can access their own journey.',
                ]);
            }
            $user = $authUser;
        }

        // Get the last 3 days including today
        $dates = [
            Carbon::today()->subDays(2)->toDateString(),
            Carbon::today()->subDays(1)->toDateString(),
            Carbon::today()->toDateString(),
        ];

        // Fetch and group technician_areas data
        $locations = DB::table('technician_areas')
            ->where('user_id', $user->id)
            ->whereIn(DB::raw('DATE(created_at)'), $dates)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->created_at)->toDateString();
            });

        // Structure journey output using actual dates
        $journey = [];
        foreach ($dates as $date) {
            $journey[$date] = $locations->get($date) ?? [];
        }

        return response()->json([
            'status_code' => 1,
            'data' => [
                'technician_id' => $user->id,
                'name' => $user->name,
                'journey' => $journey
            ],
            'message' => 'Technician journey fetched successfully.'
        ]);
    }
}
