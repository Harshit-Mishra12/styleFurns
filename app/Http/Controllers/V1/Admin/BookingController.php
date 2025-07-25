<?php

namespace App\Http\Controllers\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingImage;
use App\Models\BookingPart;
use App\Models\Customer;
use App\Models\Skill;
use App\Models\User;
use App\Services\BookingPartsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\TechnicianAssignmentService;
use Illuminate\Support\Facades\Log;




class BookingController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'name'               => 'required|string',
            'damage_desc'        => 'nullable|string',
            'scheduled_date'     => 'required|date',
            'customer_name'      => 'required|string',
            'slots_required'      => 'required|numeric',
            'customer_email'     => 'nullable|email',
            'customer_phone'     => 'required|string',
            'customer_address'   => 'required|string',
            'customer_area'      => 'nullable|string',
            'customer_latitude'  => 'required|numeric|between:-90,90',
            'customer_longitude' => 'required|numeric|between:-180,180',
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

            'before_images'               => 'nullable|array',
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
            $status = $request->has('selected_slot') ? 'pending' : 'waiting_approval';
            $booking = Booking::create([
                'name'              => $request->name,
                'damage_desc'       => $request->damage_desc,
                // 'scheduled_date'    => $request->scheduled_date,
                'scheduled_date' => now()->toDateString(),
                'status'            => $status,
                'reason' => null,
                'current_technician_id' => $request->selected_slot['technician_id'] ?? null,
                'slots_required'    => $request->slots_required,
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
                    'slot_date'        => $slot['date'],
                    'time_start'  => $slot['time_start'],
                    'time_end'    => $slot['time_end'],
                ]);
                Helper::sendPushNotification(1, [$slot['technician_id']]);
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



    // public function index(Request $request)
    // {
    //     $query = Booking::with([
    //         'customer',
    //         'parts',
    //         'images',
    //         'technician',
    //         'bookingAssignments'
    //     ])->orderBy('created_at', 'desc');

    //     // 🔍 Optional status filter
    //     if ($request->filled('status')) {
    //         $validStatuses = [
    //             'pending',
    //             'completed',
    //             'waiting_approval',
    //             'waiting_parts',
    //             'rescheduling_required',
    //             'cancelled',
    //         ];

    //         if (in_array($request->status, $validStatuses)) {
    //             $query->where('status', $request->status);
    //         } else {
    //             return response()->json([
    //                 'status_code' => 0,
    //                 'message' => 'Invalid status filter.',
    //             ], 400);
    //         }
    //     }

    //     // 🔍 Optional technician_id filter
    //     if ($request->filled('technician_id')) {
    //         $query->where('current_technician_id', $request->technician_id);
    //     }

    //     $bookings = $query->get();

    //     return response()->json([
    //         'status_code' => 1,
    //         'data' => [
    //             'bookings' => $bookings
    //         ],
    //         'message' => 'Bookings fetched successfully.'
    //     ]);
    // }


    public function index(Request $request)
    {
        // ✅ Set pagination defaults
        $limit   = $request->input('limit', 10);       // default limit = 10
        $pageNo  = $request->input('page_no', 1);      // default page = 1
        $offset  = ($pageNo - 1) * $limit;

        $query = Booking::with([
            'customer',
            'parts',
            'images',
            'technician',
            'bookingAssignments'
        ])->orderBy('created_at', 'desc');

        // 🔍 Optional status filter
        if ($request->filled('status')) {
            $validStatuses = [
                'pending',
                'completed',
                'waiting_approval',
                'waiting_parts',
                'rescheduling_required',
                'cancelled',
            ];

            if (in_array($request->status, $validStatuses)) {
                $query->where('status', $request->status);
            } else {
                return response()->json([
                    'status_code' => 2,
                    'message'     => 'Invalid status filter.',
                ]);
            }
        }

        // 🔍 Optional technician_id filter
        if ($request->filled('technician_id')) {
            $query->where('current_technician_id', $request->technician_id);
        }

        // ✅ Get total count before pagination
        $total = $query->count();

        // ✅ Apply limit and offset for pagination
        $bookings = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Bookings fetched successfully.',
            'data'        => [
                'bookings'       => $bookings,
                'pagination' => [
                    'total_records' => $total,
                    'limit'         => (int) $limit,
                    'page_no'       => (int) $pageNo,
                    'total_pages'   => ceil($total / $limit),
                ]
            ]
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

    public function updateBooking(Request $request, $id)
    {
        $booking = Booking::with(['customer', 'images'])->findOrFail($id);

        $request->validate([
            'name'               => 'sometimes|required|string',
            'damage_desc'        => 'nullable|string',
            'scheduled_date'     => 'sometimes|required|date',
            'remark'             => 'nullable|string|max:1000',
            'status_comment'     => 'nullable|string|max:1000',
            'required_skills'    => 'nullable|array',
            'required_skills.*'  => 'exists:skills,id',

            'technician_id'      => 'nullable|exists:users,id',

            // Customer fields
            'customer_name'      => 'sometimes|required|string',
            'customer_email'     => 'nullable|email',
            'customer_phone'     => 'sometimes|required|string',
            'customer_address'   => 'sometimes|required|string',
            'customer_area'      => 'sometimes|required|string',
            'customer_latitude'  => 'nullable|numeric|between:-90,90',
            'customer_longitude' => 'nullable|numeric|between:-180,180',

            'parts'              => 'nullable|array',
            'parts.*.id'         => 'nullable|exists:booking_parts,id',
            'parts.*.name'       => 'required_with:parts|string',
            'parts.*.serial_number' => 'required_with:parts|string',
            'parts.*.quantity'   => 'nullable|numeric|min:0.01',
            'parts.*.unit_type'  => 'nullable|in:unit,gram,kg,ml,liter,meter',
            'parts.*.price'      => 'nullable|numeric|min:0',
            'parts.*.provided_by' => 'nullable|in:admin,technician,customer,unknown',
            'parts.*.is_provided' => 'nullable|boolean',
            'parts.*.is_required' => 'nullable|boolean',
            'parts.*.notes'      => 'nullable|string|max:1000',

            'before_images'      => 'nullable|array',
            'before_images.*'    => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        DB::beginTransaction();

        try {
            $updatedFields = [];

            // Update booking core fields
            $fieldsToUpdate = array_filter($request->only([
                'name',
                'damage_desc',
                'scheduled_date',
                'remark',
                'status_comment',
            ]));
            if (!empty($fieldsToUpdate)) {
                $booking->update($fieldsToUpdate);
                $updatedFields = array_merge($updatedFields, array_keys($fieldsToUpdate));
            }

            // Technician assignment
            if ($request->filled('technician_id') && $booking->current_technician_id != $request->technician_id) {
                $booking->current_technician_id = $request->technician_id;
                $booking->save();
                $updatedFields[] = 'technician_id';
            }

            // Required skills
            if ($request->has('required_skills')) {
                $booking->required_skills = $request->required_skills;
                $booking->save();
                $updatedFields[] = 'required_skills';
            }

            // ✅ Update customer data
            if ($booking->customer) {
                $customerUpdate = array_filter($request->only([
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'customer_address',
                    'customer_area',
                    'customer_latitude',
                    'customer_longitude',
                ]));

                if (!empty($customerUpdate)) {
                    $booking->customer->update([
                        'name'      => $customerUpdate['customer_name'] ?? $booking->customer->name,
                        'email'     => $customerUpdate['customer_email'] ?? $booking->customer->email,
                        'phone'     => $customerUpdate['customer_phone'] ?? $booking->customer->phone,
                        'address'   => $customerUpdate['customer_address'] ?? $booking->customer->address,
                        'area'      => $customerUpdate['customer_area'] ?? $booking->customer->area,
                        'latitude'  => $customerUpdate['customer_latitude'] ?? $booking->customer->latitude,
                        'longitude' => $customerUpdate['customer_longitude'] ?? $booking->customer->longitude,
                    ]);
                    $updatedFields[] = 'customer';
                }
            }

            // 🔁 Sync parts
            if ($request->has('parts')) {
                $incomingPartIds = collect($request->parts)->pluck('id')->filter()->toArray();
                $existingParts = $booking->parts()->pluck('id')->toArray();

                // Delete removed parts
                $partsToDelete = array_diff($existingParts, $incomingPartIds);
                BookingPart::whereIn('id', $partsToDelete)->delete();

                // Create or update parts
                foreach ($request->parts as $partData) {
                    $partAttributes = [
                        'part_name'     => $partData['name'],
                        'serial_number' => $partData['serial_number'] ?? null,
                        'quantity'      => $partData['quantity'] ?? 1,
                        'unit_type'     => $partData['unit_type'] ?? 'unit',
                        'price'         => $partData['price'] ?? null,
                        'added_by'      => auth()->id(),
                        'added_source'  => 'admin',
                        'provided_by'   => $partData['provided_by'] ?? 'unknown',
                        'is_provided'   => $partData['is_provided'] ?? false,
                        'is_required'   => $partData['is_required'] ?? true,
                        'notes'         => $partData['notes'] ?? null,
                    ];

                    if (!empty($partData['id'])) {
                        BookingPart::where('id', $partData['id'])->update($partAttributes);
                    } else {
                        $booking->parts()->create($partAttributes);
                    }
                }

                $updatedFields[] = 'parts';
            }

            // 📷 Replace before_images
            if ($request->hasFile('before_images')) {
                // Delete existing before images from DB and optionally from disk
                $oldImages = $booking->images()->where('type', 'before')->get();
                foreach ($oldImages as $img) {
                    // Optionally delete from storage: Storage::delete($img->image_url);
                    $img->delete();
                }

                foreach ($request->file('before_images') as $imageFile) {
                    $imageUrl = Helper::saveImageToServer($imageFile, 'uploads/bookings/');
                    BookingImage::create([
                        'booking_id'  => $booking->id,
                        'image_url'   => $imageUrl,
                        'type'        => 'before',
                        'uploaded_by' => auth()->id(),
                    ]);
                }

                $updatedFields[] = 'before_images';
            }

            DB::commit();

            return response()->json([
                'status_code'    => 1,
                'message'        => 'Booking updated successfully.',
                'updated_fields' => $updatedFields,
                'data'           => ['booking_id' => $booking->id],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message'     => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function getAvailableSlots(Request $request)
    {
        // ✅ Step 1: Validate request inputs
        $request->validate([
            'skills_required'    => 'required|array',
            'skills_required.*'  => 'integer|exists:skills,id',
            'customer_latitude'  => 'required|numeric',
            'customer_longitude' => 'required|numeric',
            'required_slots'     => 'required|integer|min:1',
        ]);

        $requiredSkillIds = $request->skills_required;
        $customerLat = $request->customer_latitude;
        $customerLng = $request->customer_longitude;
        $requiredSlots = (int) $request->required_slots;
        $maxDailySlots = 14;
        $today = now()->toDateString();

        // ✅ Step 2: Fetch online technicians with their skills and last known location
        $technicians = User::with(['technicianSkills', 'latestTechnicianArea'])
            ->where('role', 'technician')
            ->where('job_status', 'online')
            ->where('status', 'active')
            ->get();

        // dd($technicians);
        $results = [];

        foreach ($technicians as $technician) {
            $technicianSkillIds = $technician->technicianSkills->pluck('skill_id')->toArray();
            $skillMatched = array_intersect($requiredSkillIds, $technicianSkillIds);

            if (empty($skillMatched)) {
                continue; // skip if no matching skill
            }

            // ✅ Step 3: Count booked slots for the day
            // $assignmentsToday = BookingAssignment::where('user_id', $technician->id)
            //     ->whereDate('slot_date', $today)
            //     ->whereHas('booking', fn($q) => $q->where('status', 'pending'))
            //     ->get();
            $assignmentsToday = BookingAssignment::where('user_id', $technician->id)
                ->whereDate('slot_date', $today)
                ->where('status', '!=', 'unassigned') // ✅ filter out unassigned
                ->whereHas('booking', fn($q) => $q->where('status', 'pending'))
                ->get();


            $bookedSlotCount = $assignmentsToday->sum(fn($a) => $a->booking->slots_required ?? 1);
            // $bookedSlots = $assignmentsToday->map(function ($assignment) {
            //     return [
            //         'date' => $assignment->slot_date,
            //         'time' => \Carbon\Carbon::parse($assignment->time_start)->format('h:i A'),
            //     ];
            // })->values()->all();
            // $bookedSlots = $assignmentsToday->map(function ($assignment) {
            //     return [
            //         'date' => \Carbon\Carbon::parse($assignment->slot_date)->format('Y-m-d'),
            //         'time' => \Carbon\Carbon::parse($assignment->time_start)->format('h:i A'),
            //     ];
            // })->values()->all();
            $bookedSlots = [];

            foreach ($assignmentsToday as $assignment) {
                $date = \Carbon\Carbon::parse($assignment->slot_date)->toDateString(); // ensures no duplicate time part
                $start = new \DateTime($date . ' ' . $assignment->time_start);
                $end = new \DateTime($date . ' ' . $assignment->time_end);

                while ($start < $end) {
                    $bookedSlots[] = [
                        'date' => $start->format('Y-m-d'),
                        'time' => $start->format('g:i A'),
                    ];
                    $start->modify('+1 hour');
                }
            }



            $freeSlots = max(0, $maxDailySlots - $bookedSlotCount);

            if ($freeSlots < $requiredSlots) {
                continue;
            }

            // ✅ Step 4: Determine technician's last known location
            $lastLat = null;
            $lastLng = null;

            if ($assignmentsToday->isNotEmpty()) {

                $lastBooking = Booking::where('current_technician_id', $technician->id)
                    ->whereDate('scheduled_date', now()->toDateString())
                    ->where('status', 'pending')
                    ->orderByDesc('updated_at')
                    ->with('customer')
                    ->first();

                if ($lastBooking && $lastBooking->customer) {
                    $sourceLat = $lastBooking->customer->latitude;
                    $sourceLng = $lastBooking->customer->longitude;
                } else {
                    // If lastBooking is null or doesn't have a customer
                    $sourceLat = 45.493208;
                    $sourceLng = -73.853039;
                }
            } elseif (
                $technician->latestTechnicianArea &&
                \Carbon\Carbon::parse($technician->latestTechnicianArea->created_at)->isToday()
            ) {
                $sourceLat = $technician->latestTechnicianArea->latitude;
                $sourceLng = $technician->latestTechnicianArea->longitude;
            } else {
                $sourceLat = 45.493208; // Admin base location
                $sourceLng = -73.853039;
            }


            // dd([
            //     'techncian_id' => $technician->id,
            //     'lat2' => $sourceLat,
            //     'lon2' => $sourceLng,
            // ]);

            // ✅ Step 5: Calculate distance
            $distanceKm = $this->calculateDistance($customerLat, $customerLng, $sourceLat, $sourceLng); // Haversine
            // $distanceKm = $this->calculateDistance(
            //     (float) $customerLat,
            //     (float) $customerLng,
            //     (float) $sourceLat,
            //     (float) $sourceLng
            // );

            $totalSkillsRequired = count($requiredSkillIds);

            // ✅ Step 6: Scoring
            $skillScore = ($totalSkillsRequired > 0) ? (count($skillMatched) / $totalSkillsRequired) * 100 : 0;
            $slotScore = $bookedSlotCount * -10;
            $distanceScore = $distanceKm * -10;
            $totalScore = $skillScore + $slotScore + $distanceScore;

            $results[] = [
                'technician_id'     => $technician->id,
                'tech_name'         => $technician->name,
                'status'            => $technician->job_status,
                'skill_matched'     => Skill::whereIn('id', $skillMatched)->pluck('name')->toArray(),
                'total_skills_required' => $totalSkillsRequired,
                'booked_slots_count' => $bookedSlotCount,
                'booked_slots' => $bookedSlots,
                'free_slots'        => $freeSlots,
                'skill_score'       => round($skillScore),
                'slot_score'        => $slotScore,
                'distance_km'       => round($distanceKm, 2),
                'distance_score'    => round($distanceScore),
                'total_score'       => round($totalScore),
                'technician' => $technician
            ];
        }

        // ✅ Step 7: Sort by total score
        usort($results, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Technician availability fetched successfully.',
            'data'        => $results,
        ]);
    }

    // private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    // {
    //     // dd([
    //     //     'lat1' => $lat1,
    //     //     'lon1' => $lon1,
    //     //     'lat2' => $lat2,
    //     //     'lon2' => $lon2,
    //     // ]);

    //     $earthRadius = 6371; // km

    //     // Log::debug('Calculating distance with coordinates:', [
    //     //     'lat1' => $lat1,
    //     //     'lon1' => $lon1,
    //     //     'lat2' => $lat2,
    //     //     'lon2' => $lon2,
    //     // ]);

    //     $dLat = deg2rad($lat2 - $lat1);
    //     $dLon = deg2rad($lon2 - $lon1);

    //     $a = sin($dLat / 2) * sin($dLat / 2) +
    //         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
    //         sin($dLon / 2) * sin($dLon / 2);

    //     $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    //     return $earthRadius * $c;
    // }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }


    // public function getAvailableSlots(Request $request)
    // {

    //     //step -1 fetch all online techncians
    //     // step -2- remove all techncian with zero skills matching
    //     //step 3 create score-->
    //     // a-drive skills score for each techncian
    //     // b->

    //     // In production: Validate location inputs
    //     $latitude = $request->get('latitude');   // e.g. 19.0760
    //     $longitude = $request->get('longitude'); // e.g. 72.8777

    //     // 1. Fetch nearby technicians with role = technician (you can filter by skill/availability/etc.)
    //     // This is dummy for now. Replace with actual location-based filtering later.
    //     $technicians = User::where('role', 'technician')->take(3)->get();

    //     // 2. Dummy booked slot data for now
    //     $today = date('Y-m-d');

    //     $dummyBookedSlots = [
    //         ['date' => $today, 'time' => '10:00 AM'],
    //         ['date' => $today, 'time' => '11:00 AM'],
    //         ['date' => $today, 'time' => '12:00 PM'],
    //     ];


    //     // 3. Create dummy slot response
    //     $results = [];

    //     foreach ($technicians as $index => $technician) {
    //         $results[] = [
    //             'technician'  => $technician,
    //             'totalScore'  => 500 - ($index * 50),
    //             'skillScore'  => (2 + $index) / 5,
    //             'distance'    => (5 + $index * 3) . ' km',
    //             'freeSlots'   => 5 - $index, //for that day for that technian
    //             'bookedSlots' => $dummyBookedSlots,
    //         ];
    //     }

    //     return response()->json([
    //         'status_code' => 1,
    //         'message' => 'Nearby technician slots fetched successfully.',
    //         'data' => $results,
    //     ]);
    // }

    public function getStats(Request $request)
    {
        $technicianId = $request->input('technician_id');

        // Booking status counts
        $allStatuses = [
            'pending',
            'completed',
            'waiting_approval',
            'waiting_parts',
            'rescheduling_required',
            'cancelled'
        ];

        $statusCounts = [];

        foreach ($allStatuses as $status) {
            $query = Booking::where('status', $status);

            if (!empty($technicianId)) {
                $query->where('current_technician_id', $technicianId);
            }

            $statusCounts[$status] = $query->count();
        }

        // Technician counts
        $totalTechnicians = User::where('role', 'technician')->count(); // Excludes inactive
        $activeTechnicians = User::where('role', 'technician')->where('status', 'active')->count();
        $inactiveTechnicians = User::where('role', 'technician')->where('status', 'inactive')->count();

        $onlineTechnicians = User::where('role', 'technician')
            ->where('status', 'active')
            ->where('job_status', 'online')
            ->count();

        $offlineTechnicians = User::where('role', 'technician')
            ->where('status', 'active')
            ->where('job_status', 'offline')
            ->count();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Booking status stats fetched successfully.',
            'data'        => [
                'status' => $statusCounts,
                'technicians' => [
                    'total_technician'    => $totalTechnicians,
                    'active_technician'   => $activeTechnicians,
                    'inactive_technician' => $inactiveTechnicians,
                    'offline_technician'  => $offlineTechnicians,
                    'online_technician'   => $onlineTechnicians,
                ]
            ]
        ]);
    }

    public function updateBookingStatus(Request $request)
    {
        $request->validate([
            'booking_id'      => 'required|integer|exists:bookings,id',
            'status'          => 'required|in:completed,rescheduling_required,waiting_parts,cancelled',
            'status_comment'  => 'nullable|string|max:255',
            'price'           => 'required_if:status,completed|nullable|numeric|min:0',
            'reschedule_all_bookings' => 'nullable|boolean',
        ]);

        $booking = Booking::find($request->booking_id);

        if (!$booking) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Booking not found.',
                'data' => []
            ]);
        }

        $status = $request->status;
        $comment = $request->status_comment;
        $price = $request->price;
        $today = now()->toDateString();
        // Keep the above logic unchanged, then add this separately
        if (
            $status === 'rescheduling_required' &&
            !$request->boolean('reschedule_all_bookings')
        ) {
            Helper::sendPushNotification(3, [$booking->current_technician_id]);
        }
        if (
            $status === 'rescheduling_required' &&
            $request->boolean('reschedule_all_bookings')
        ) {
            $technicianId = $booking->current_technician_id;

            if ($technicianId) {
                Helper::sendPushNotification(8, [$technicianId]);
                $bookingDate = \Carbon\Carbon::parse($booking->slot_date)->toDateString();

                $otherAssignments = BookingAssignment::where('user_id', $technicianId)
                    ->where('status', 'assigned')
                    ->whereDate('slot_date',   $today)
                    ->get();

                foreach ($otherAssignments as $otherAssignment) {
                    $otherAssignment->update([
                        'status' => 'unassigned',
                        'reason' => 'Auto-rescheduled due to technician unavailability',
                    ]);

                    $relatedBooking = $otherAssignment->booking;
                    if ($relatedBooking && $relatedBooking->current_technician_id == $technicianId) {
                        $relatedBooking->update([
                            'status' => 'rescheduling_required',
                            'current_technician_id' => null,
                            'status_comment' =>  $comment,
                        ]);
                    }
                }

                // Mark technician offline
                \App\Models\User::where('id', $technicianId)->update(['job_status' => 'offline']);
            }
        } else if ($status === 'rescheduling_required' || $status === 'waiting_parts') {
            $assignment = BookingAssignment::where('booking_id', $booking->id)
                ->where('status', 'assigned')
                ->latest()
                ->first();

            if ($assignment) {
                $assignment->update([
                    'status' => 'unassigned',
                    'reason' => $comment ?? $status,
                ]);
            }
            if ($booking->current_technician_id) {
                Helper::sendPushNotification(3, [$booking->current_technician_id]);
            }

            $booking->update([
                'status' => $status,
                'status_comment' => $comment,
                'current_technician_id' => null,
            ]);
        } elseif ($status === 'cancelled') {
            $assignment = BookingAssignment::where('booking_id', $booking->id)
                ->where('status', 'assigned')
                ->latest()
                ->first();

            if ($assignment) {
                $assignment->update([
                    'status' => 'unassigned',
                    'reason' => $comment ?? $status,
                ]);
            }
            if ($booking->current_technician_id) {
                Helper::sendPushNotification(7, [$booking->current_technician_id]);
            }
            $booking->update([
                'status' => $status,
                'status_comment' => $comment,
                'current_technician_id' => null,
            ]);
        } elseif ($status === 'completed') {
            $booking->update([
                'status' => 'completed',
                'status_comment' => $comment,
                'price' => $price,
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Booking status updated successfully.',
            'data' => []
        ]);
    }

    // public function updateBookingStatus(Request $request)
    // {
    //     $request->validate([
    //         'booking_id'      => 'required|integer|exists:bookings,id',
    //         'status'          => 'required|in:completed,rescheduling_required',
    //         'status_comment'  => 'nullable|string|max:255',
    //         'price'           => 'required_if:status,completed|nullable|numeric|min:0',
    //     ]);

    //     $booking = Booking::find($request->booking_id);

    //     if (!$booking) {
    //         return response()->json([
    //             'status_code' => 0,
    //             'message' => 'Booking not found.',
    //             'data' => []
    //         ]);
    //     }

    //     $status = $request->status;
    //     $comment = $request->status_comment;
    //     $price = $request->price;

    //     if ($status === 'rescheduling_required') {
    //         $assignment = BookingAssignment::where('booking_id', $booking->id)
    //             ->where('status', 'assigned')
    //             ->latest()
    //             ->first();

    //         if ($assignment) {
    //             $assignment->update([
    //                 'status' => 'unassigned',
    //                 'reason' => $comment ?? 'rescheduling_required',
    //             ]);
    //         }

    //         $booking->update([
    //             'status' => 'rescheduling_required',
    //             'status_comment' => $comment,
    //             'current_technician_id' => null,
    //         ]);
    //     } elseif ($status === 'completed') {
    //         $booking->update([
    //             'status' => 'completed',
    //             'status_comment' => $comment,
    //             'price' => $price,
    //         ]);
    //     }

    //     return response()->json([
    //         'status_code' => 1,
    //         'message' => 'Booking status updated successfully.',
    //         'data' => []
    //     ]);
    // }

    public function assignTechnicianToRescheduledBooking(Request $request, $booking_id)
    {


        $request->validate([
            'selected_slot.technician_id' => 'required|integer|exists:users,id',
            'selected_slot.date'          => 'required|date',
            'selected_slot.time_start'    => 'required|date_format:H:i',
            'selected_slot.time_end'      => 'required|date_format:H:i|after:selected_slot.time_start',
        ]);



        $booking = Booking::find($booking_id);

        if (!$booking) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Booking not found.',
                'data' => []
            ]);
        }

        if ($booking->status !== 'rescheduling_required') {
            return response()->json([
                'status_code' => 2,
                'message' => 'Booking is not marked for rescheduling.',
                'data' => []
            ]);
        }

        $slot = $request->selected_slot;

        // Step 1: Assign new technician
        BookingAssignment::create([
            'booking_id'   => $booking->id,
            'user_id'      => $slot['technician_id'],
            'status'       => 'assigned',
            'assigned_at'  => now(),
            'slot_date'    => $slot['date'],
            'time_start'   => $slot['time_start'],
            'time_end'     => $slot['time_end'],
        ]);
        Helper::sendPushNotification(2, [$slot['technician_id']]);

        // Step 2: Update booking
        $booking->update([
            'status'                 => 'pending',
            'current_technician_id'  => $slot['technician_id'],
            'status_comment'         => null, // Optional: clear previous comment
        ]);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Technician assigned and booking status updated to pending.',
            'data'        => []
        ]);
    }




    public function updateTechnicianWorkStatus(Request $request)
    {
        $request->validate([
            'technician_id'   => 'required|integer|exists:users,id',
            'status'          => 'required|in:active,inactive',
            'status_comment'  => 'nullable|string|max:255',
        ]);

        $technician = User::where('role', 'technician')->find($request->technician_id);

        if (!$technician) {
            return response()->json([
                'status_code' => 2,
                'message'     => 'Technician not found.',
            ]);
        }

        $status = $request->status;
        $comment = $request->status_comment ?? ($status === 'inactive' ? 'Technician made inactive' : null);

        if ($status === 'inactive') {
            // Update bookings and assignments for technician
            $assignments = BookingAssignment::where('user_id', $technician->id)
                ->where('status', 'assigned')
                ->whereHas('booking', function ($q) {
                    $q->where('status', 'pending');
                })
                ->get();

            foreach ($assignments as $assignment) {
                $booking = $assignment->booking;

                if ($booking) {
                    $booking->update([
                        'status'                => 'rescheduling_required',
                        'status_comment'        => $comment,
                        'current_technician_id' => null,
                    ]);

                    $assignment->update([
                        'status' => 'unassigned',
                        'reason' => $comment,
                    ]);
                }
            }

            $technician->job_status = "offline";
        }

        // Save the technician's active/inactive status in a dedicated column if available
        $technician->status = $request->status; // Assuming there's a column like `is_active`
        $technician->save();

        return response()->json([
            'status_code' => 1,
            'message'     => "Technician status updated to '{$status}' successfully.",
        ]);
    }
}
