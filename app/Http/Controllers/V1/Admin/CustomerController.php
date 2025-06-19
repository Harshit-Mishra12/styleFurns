<?php

namespace App\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Skill;
use App\Models\User;
use Carbon\Carbon;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with([
            'bookings.bookingAssignments.technician'  // nested eager load
        ])->latest()->get();

        $data = $customers->map(function ($customer) {
            return [
                'id'        => $customer->id,
                'name'      => $customer->name,
                'email'     => $customer->email,
                'phone'     => $customer->phone,
                'address'   => $customer->address,
                'area'      => $customer->area,
                'latitude'  => $customer->latitude,
                'longitude' => $customer->longitude,
                'created_at' => $customer->created_at->toDateTimeString(),
                'bookings'  => $customer->bookings->map(function ($booking) {
                    return [
                        'id'         => $booking->id,
                        'name'       => $booking->name,
                        'status'     => $booking->status,
                        'scheduled_date' => $booking->scheduled_date,
                        'remark'     => $booking->remark,
                        'assignments' => $booking->bookingAssignments->map(function ($assign) {
                            return [
                                'id'         => $assign->id,
                                'technician' => $assign->technician ? [
                                    'id'    => $assign->technician->id,
                                    'name'  => $assign->technician->name,
                                    'email' => $assign->technician->email,
                                    'mobile' => $assign->technician->mobile,
                                ] : null,
                                'status'     => $assign->status,
                                'date'       => $assign->date,
                                'time_start' => $assign->time_start,
                                'time_end'   => $assign->time_end,
                                'responded_at' => $assign->responded_at,
                                'assigned_at'  => $assign->assigned_at,
                            ];
                        }),
                    ];
                }),
            ];
        });

        return response()->json([
            'status_code' => 1,
            'message'     => 'Customer data with bookings and assignments fetched successfully.',
            'data'        => $data
        ]);
    }
}
