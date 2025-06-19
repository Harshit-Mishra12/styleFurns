<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingImage;
use App\Models\BookingPart;
use App\Models\Customer;
use App\Models\User;
use App\Services\BookingPartsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\TechnicianAssignmentService;



class TechnicianController extends Controller
{


    public function index(Request $request)
    {
        $query = User::with(['skills'])
            ->where('role', 'technician');

        // 🔍 Search filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('mobile', 'like', "%$search%");
            });
        }

        if ($request->filled('skill_id')) {
            $query->whereHas('skills', function ($q) use ($request) {
                $q->whereIn('skills.id', (array) $request->skill_id);
            });
        }

        $technicians = $query->get();

        $data = $technicians->map(function ($technician) {
            // ✅ Completed bookings
            $completedCount = $technician->bookings()
                ->where('status', 'completed')->count();

            // ✅ Pending bookings
            $pendingCount = $technician->bookings()
                ->whereIn('status', ['pending', 'waiting_parts', 'rescheduling_required'])
                ->count();

            // ✅ Latest location from technician_areas
            $latestLocation = $technician->technicianAreas()->latest('created_at')->first();
            $latitude = $latestLocation?->latitude;
            $longitude = $latestLocation?->longitude;

            return [
                'id'                 => $technician->id,
                'name'               => $technician->name,
                'email'              => $technician->email,
                'mobile'             => $technician->mobile,
                'profile_picture'    => $technician->profile_picture,
                'job_status'         => $technician->job_status,
                'current_latitude'   => $latitude,
                'current_longitude'  => $longitude,
                'skills'             => $technician->skills->pluck('name'),
                'completed_bookings' => $completedCount,
                'pending_bookings'   => $pendingCount,
                'joined_at'          => $technician->created_at->toDateString(),
            ];
        });

        return response()->json([
            'status_code' => 1,
            'message'     => 'Technicians fetched successfully.',
            'data'        => $data
        ]);
    }
}
