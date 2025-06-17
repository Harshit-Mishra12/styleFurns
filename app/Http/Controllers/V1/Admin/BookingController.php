<?php

namespace App\Http\Controllers\V1\Admin;

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



class BookingController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'name'               => 'required|string',
            'damage_desc'        => 'nullable|string',
            'scheduled_date'     => 'required|date',
            'customer_name'      => 'required|string',
            'customer_email'     => 'nullable|email',
            'customer_phone'     => 'required|string',
            'customer_address'   => 'required|string',
            'customer_area'      => 'required|string',
            'customer_latitude'  => 'nullable|numeric|between:-90,90',
            'customer_longitude' => 'nullable|numeric|between:-180,180',
            'remark'             => 'nullable|string|max:1000',
            'status_comment'     => 'nullable|string|max:1000',
            'required_skills'    => 'nullable|array',
            'required_skills.*'  => 'exists:skills,id',

            'selected_slot'                => 'nullable|array',
            'selected_slot.technician_id' => 'required_with:selected_slot|exists:users,id',
            'selected_slot.date'          => 'required_with:selected_slot|date',
            'selected_slot.time_start'    => 'required_with:selected_slot|date_format:H:i',
            'selected_slot.time_end'      => 'required_with:selected_slot|date_format:H:i',

            'parts'                       => 'nullable|array',
            'parts.*.name'                => 'required_with:parts|string',
            'parts.*.serial_number'       => 'required_with:parts|string',
            'parts.*.quantity'            => 'nullable|numeric|min:0.01',
            'parts.*.unit_type'           => 'nullable|in:unit,gram,kg,ml,liter,meter',
            'parts.*.price'               => 'nullable|numeric|min:0',
            'parts.*.provided_by'         => 'nullable|in:admin,technician,customer,unknown',
            'parts.*.is_provided'         => 'nullable|boolean',
            'parts.*.is_required'         => 'nullable|boolean',
            'parts.*.notes'               => 'nullable|string|max:1000',

            'before_images'               => 'required|array',
            'before_images.*'             => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        DB::beginTransaction();

        try {
            // 1. Create customer
            $customer = Customer::create([
                'name'      => $request->customer_name,
                'email'     => $request->customer_email,
                'phone'     => $request->customer_phone,
                'address'   => $request->customer_address,
                'latitude'  => $request->customer_latitude,
                'longitude' => $request->customer_longitude,
                'area'      => $request->customer_area,
            ]);

            // 2. Create booking
            $booking = Booking::create([
                'name'              => $request->name,
                'damage_desc'       => $request->damage_desc,
                'scheduled_date'    => $request->scheduled_date,
                'status'            => 'waiting_approval',
                'current_technician_id' => $request->selected_slot['technician_id'] ?? null,
                'slots_required'    => 1,
                'price'             => 0.00,
                'customer_id'       => $customer->id,
                'is_active'         => false,
                'remark'            => $request->remark,
                'status_comment'    => $request->status_comment,
                'required_skills'   => $request->required_skills,
            ]);

            // 3. Add parts
            if ($request->has('parts')) {
                foreach ($request->parts as $part) {
                    BookingPart::create([
                        'booking_id'    => $booking->id,
                        'part_name'     => $part['name'],
                        'serial_number' => $part['serial_number'] ?? null,
                        'quantity'      => $part['quantity'] ?? 1,
                        'unit_type'     => $part['unit_type'] ?? 'unit',
                        'price'         => $part['price'] ?? null,
                        'added_by'      => auth()->id(),
                        'added_source'  => 'admin',
                        'provided_by'   => $part['provided_by'] ?? 'unknown',
                        'is_provided'   => isset($part['is_provided']) ? (bool) $part['is_provided'] : false,
                        'is_required'   => isset($part['is_required']) ? (bool) $part['is_required'] : true,
                        'notes'         => $part['notes'] ?? null,
                    ]);
                }
            }

            // 4. Save before images
            if ($request->hasFile('before_images')) {
                foreach ($request->file('before_images') as $imageFile) {
                    $imageUrl = Helper::saveImageToServer($imageFile, 'uploads/bookings/');
                    BookingImage::create([
                        'booking_id'  => $booking->id,
                        'image_url'   => $imageUrl,
                        'type'        => 'before',
                        'uploaded_by' => auth()->id(),
                    ]);
                }
            }

            // 5. If selected_slot is present, assign technician
            if ($request->has('selected_slot')) {
                $slot = $request->selected_slot;
                BookingAssignment::create([
                    'booking_id'  => $booking->id,
                    'user_id'     => $slot['technician_id'],
                    'status'      => 'assigned',
                    'assigned_at' => now(),
                    'date'        => $slot['date'],
                    'time_start'  => $slot['time_start'],
                    'time_end'    => $slot['time_end'],
                ]);
            }

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'data'        => ['booking_id' => $booking->id],
                'message'     => 'Booking created successfully. Awaiting admin approval.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 2,
                'message'     => 'Booking creation failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function index()
    {
        $bookings = Booking::with(['customer', 'parts', 'images', 'technician'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'bookings' => $bookings
            ],
            'message' => 'All bookings fetched successfully.'
        ]);
    }
    public function show($id)
    {
        $booking = Booking::with(['customer', 'parts', 'images', 'technician', 'technicianHistory.user'])->find($id);

        if (!$booking) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Booking not found.'
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'data' => [
                'booking' => $booking
            ],
            'message' => 'Booking details fetched successfully.'
        ]);
    }

    public function assignNearestTechnician($booking_id, TechnicianAssignmentService $assignmentService)
    {
        $booking = Booking::with('customer')->find($booking_id);

        if (!$booking || !$booking->customer) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Booking or customer not found.',
            ]);
        }

        // ✅ Step 0: If booking already completed, no further action
        if ($booking->status === 'completed') {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'This booking has already been completed. No reassignment needed.',
            ]);
        }

        // ✅ Step 1: Check if booking is already actively assigned
        $existingAssignment = BookingAssignment::where('booking_id', $booking->id)
            ->where('status', 'assigned')
            ->latest('assigned_at')
            ->with('user')
            ->first();

        if ($existingAssignment) {
            $tech = $existingAssignment->user;
            return response()->json([
                'status_code' => 2,
                'data' => [
                    'technician_id' => $tech->id,
                    'technician_name' => $tech->name,
                    'technician_email' => $tech->email,
                ],
                'message' => 'This booking is already assigned to a technician.',
            ]);
        }

        // ✅ Step 2: Exclude technicians who already failed this job today
        $excludedTechIds = BookingAssignment::where('booking_id', $booking->id)
            ->whereDate('created_at', now()->toDateString())
            ->whereNotIn('status', ['assigned', 'completed'])
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // ✅ Step 3: Assign nearest eligible technician
        $technician = $assignmentService->assignNearestTechnician($booking, $excludedTechIds);

        if (!$technician) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'No available technician found for reassignment today.',
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'data' => [
                'technician_id' => $technician->id,
                'technician_name' => $technician->name,
                'technician_email' => $technician->email,
            ],
            'message' => 'Technician assigned successfully.',
        ]);
    }

    public function update(Request $request, $booking_id)
    {

        dd($request->all());


        $request->validate([
            // Booking Fields
            'name' => 'nullable|string',
            'damage_desc' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'status' => 'nullable|string',
            'price' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',

            // Customer Fields
            'customer' => 'nullable|array',
            'customer.name' => 'nullable|string',
            'customer.email' => 'nullable|email',
            'customer.phone' => 'nullable|string',
            'customer.address' => 'nullable|string',
            'customer.area' => 'nullable|string',
            'customer.latitude' => 'nullable|numeric',
            'customer.longitude' => 'nullable|numeric',

            // Parts
            'parts' => 'nullable|array',
            'parts.*.id' => 'nullable|exists:booking_parts,id',
            'parts.*.name' => 'required|string',
            'parts.*.serial_number' => 'nullable|string',
            'parts.*.quantity' => 'nullable|numeric|min:0.01',
            'parts.*.unit_type' => 'nullable|in:unit,gram,kg,ml,liter,meter',
            'parts.*.price' => 'nullable|numeric',
            'parts.*.provided_by' => 'nullable|in:admin,technician,customer,unknown',
            'parts.*.is_provided' => 'nullable|boolean',
            'parts.*.is_required' => 'nullable|boolean',
            'parts.*.notes' => 'nullable|string',

            // Images
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_type' => 'required_with:images|in:before,after',

            // Assignments
            'assignments' => 'nullable|array',
            'assignments.*.id' => 'required|exists:booking_assignments,id',
            'assignments.*.status' => 'required|string',
            'assignments.*.reason' => 'nullable|string',
            'assignments.*.comment' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $booking = Booking::with('customer')->findOrFail($booking_id);

            // Booking Update
            $booking->update($request->only([
                'name',
                'damage_desc',
                'scheduled_date',
                'status',
                'price',
                'is_active'
            ]));

            // Customer Update
            if ($request->has('customer')) {
                $booking->customer->update($request->customer);
            }

            // Parts Update / Add


            if ($request->has('parts')) {
                foreach ($request->parts as $part) {
                    if (!empty($part['id'])) {
                        BookingPart::where('id', $part['id'])->update([
                            'part_name' => $part['name'],
                            'serial_number' => $part['serial_number'] ?? null,
                            'quantity' => $part['quantity'] ?? 1,
                            'unit_type' => $part['unit_type'] ?? 'unit',
                            'price' => $part['price'] ?? null,
                            'provided_by' => $part['provided_by'] ?? 'unknown',
                            'is_provided' => $part['is_provided'] ?? false,
                            'is_required' => $part['is_required'] ?? true,
                            'notes' => $part['notes'] ?? null,
                        ]);
                    } else {
                        BookingPart::create([
                            'booking_id' => $booking->id,
                            'part_name' => $part['name'],
                            'serial_number' => $part['serial_number'] ?? null,
                            'quantity' => $part['quantity'] ?? 1,
                            'unit_type' => $part['unit_type'] ?? 'unit',
                            'price' => $part['price'] ?? null,
                            'provided_by' => $part['provided_by'] ?? 'unknown',
                            'is_provided' => $part['is_provided'] ?? false,
                            'is_required' => $part['is_required'] ?? true,
                            'notes' => $part['notes'] ?? null,
                            'added_by' => auth()->id(),
                            'added_source' => 'admin',
                        ]);
                    }
                }
            }

            // Images Upload
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $imageUrl = Helper::saveImageToServer($imageFile, 'uploads/bookings/');
                    BookingImage::create([
                        'booking_id' => $booking->id,
                        'image_url' => $imageUrl,
                        'type' => $request->image_type,
                        'uploaded_by' => auth()->id(),
                    ]);
                }
            }

            // Booking Assignment Updates
            if ($request->has('assignments')) {
                foreach ($request->assignments as $a) {
                    BookingAssignment::where('id', $a['id'])->update([
                        'status' => $a['status'],
                        'reason' => $a['reason'] ?? null,
                        'comment' => $a['comment'] ?? null,
                        'admin_updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Booking and related data updated successfully.',
                'data' => $booking->fresh(['customer', 'parts', 'images', 'assignments'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to update booking: ' . $e->getMessage(),
                'data' => []
            ]);
        }
    }
}
