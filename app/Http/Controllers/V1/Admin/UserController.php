<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBankDetails;
use App\Models\UserDocuments;
use Illuminate\Http\Request;



class UserController extends Controller
{
    // public function fetchAllUsers(Request $request)
    // {
    //     // Validate pagination and search parameters
    //     $request->validate([
    //         'per_page' => 'integer|min:1',
    //         'page' => 'integer|min:1',
    //         'search_name' => 'nullable|string|max:255',
    //     ]);

    //     // Get pagination parameters
    //     $perPage = $request->input('per_page'); // Default to 15 items per page
    //     $page = $request->input('page'); // Default to the first page
    //     $searchName = $request->input('search_name', '');

    //     // Query to get the total count of users
    //     $totalUsersCount = User::where('role', 'USER')->count();

    //     // Query to filter users by role and optionally by search_name
    //     $query = User::where('role', 'USER');

    //     if ($searchName) {
    //         $query->where('name', 'LIKE', "%{$searchName}%");
    //     }
    //     // return $query->get();
    //     // Paginate the results
    //     $users = $query->paginate($perPage, ['*'], 'page', $page);

    //     // Count verified and unverified users
    //     $verifiedCount = User::where('role', 'USER')->where('status', 'ACTIVE')->count();
    //     $unverifiedCount = User::where('role', 'USER')->where('status', 'VERIFICATIONPENDING')->count();

    //     // Format the response
    //     return response()->json([
    //         'status_code' => 1,
    //         'message' => 'Users retrieved successfully',
    //         'data' => [
    //             'usersList' => $users->items(), // User list
    //             'totalUsers' => $totalUsersCount, // Total number of users irrespective of search
    //             'verifiedCount' => $verifiedCount, // Verified user count
    //             'unverifiedCount' => $unverifiedCount, // Unverified user count
    //         ],
    //         'current_page' => $users->currentPage(),
    //         'last_page' => $users->lastPage(),
    //         'per_page' => $users->perPage(),
    //         'total' => $users->total(), // Total number of filtered users for pagination
    //     ]);
    // }

    public function fetchAllUsers(Request $request)
    {
        // Validate pagination, search, and type parameters
        $request->validate([
            'per_page' => 'integer|min:1',
            'page' => 'integer|min:1',
            'search_name' => 'nullable|string|max:255',
            'type' => 'nullable|in:VERIFIED,UNVERIFIED,BLOCKED,ALL', // Added 'ALL'
        ]);

        // Get pagination and filter parameters
        $perPage = $request->input('per_page', 15); // Default to 15 items per page
        $page = $request->input('page', 1); // Default to the first page
        $searchName = $request->input('search_name', '');
        $type = $request->input('type', 'ALL'); // Default type is 'ALL'

        // Base query to filter users by role and is_verified
        // $query = User::where('role', 'USER')->where('is_verified', true);
        $query = User::where('role', 'USER')
        ->where('is_verified', true)
        ->orderBy('created_at', 'desc');

        // Apply search filter if provided
        if ($searchName) {
            $query->where('name', 'LIKE', "%{$searchName}%");
        }

        // Apply type filter if provided
        if ($type !== 'ALL') { // Only apply type filter if it's not 'ALL'
            if ($type === 'VERIFIED') {
                $query->where('is_bank_details_verified', true)
                    ->where('doc_status', 'VERIFIED')
                    ->where('status', '<>', 'INACTIVE'); // Exclude BLOCKED users
            } elseif ($type === 'UNVERIFIED') {
                $query->where(function ($subQuery) {
                    $subQuery->where('is_bank_details_verified', false)
                        ->orWhere('doc_status', '<>', 'VERIFIED');
                })->where('status', '<>', 'INACTIVE'); // Exclude BLOCKED users
            } elseif ($type === 'BLOCKED') {
                $query->where('status', 'INACTIVE'); // Only BLOCKED users
            }
        }

        // Paginate the results
        $users = $query->paginate($perPage, ['*'], 'page', $page);

        // Map users to include custom fields
        $modifiedUsers = $users->map(function ($user) {
            // Determine user_status
            $user_status = ($user->is_bank_details_verified && $user->doc_status === 'VERIFIED')
                ? 'VERIFIED'
                : 'UNVERIFIED';

            // Update status if event status is INACTIVE
            $status = ($user->status === 'INACTIVE') ? 'BLOCKED' : $user->status;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'dob' => $user->dob,
                'profile_picture' => $user->profile_picture,
                'created_at' => $user->created_at,
                'status' => $status,
                'user_status' => $user_status,
                'is_verified' => true, // Ensure is_verified is true for all users
            ];
        });

        $totalUsers = User::where('role', 'USER')
        ->where('is_verified', true)
        ->count();
        // Count verified, unverified, and blocked users
        $verifiedCount = User::where('role', 'USER')
            ->where('is_verified', true)
            ->where('is_bank_details_verified', true)
            ->where('doc_status', 'VERIFIED')
            ->where('status', '<>', 'INACTIVE')
            ->count();

        $unverifiedCount = User::where('role', 'USER')
            ->where('is_verified', true)
            ->where(function ($subQuery) {
                $subQuery->where('is_bank_details_verified', false)
                    ->orWhere('doc_status', '<>', 'VERIFIED');
            })
            ->where('status', '<>', 'INACTIVE')
            ->count();

        $blockedCount = User::where('role', 'USER')
            ->where('is_verified', true)
            ->where('status', 'INACTIVE')
            ->count();

        $totalUsersCount = User::where('role', 'USER')->where('is_verified', true)->count();

        // Format the response
        return response()->json([
            'status_code' => 1,
            'message' => 'Users retrieved successfully',
            'data' => [
                'usersList' => $modifiedUsers, // Modified user list with additional fields
                'totalUsers' => $totalUsers, // Total number of filtered users for pagination
                'verifiedCount' => $verifiedCount, // Verified user count
                'unverifiedCount' => $unverifiedCount, // Unverified user count
                'blockedCount' => $blockedCount, // Blocked user count
                'allCount' => $totalUsersCount, // Total user count for 'ALL'
            ],
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(), // Total number of filtered users
        ]);
    }





    public function fetchUserDetails($id)
    {
        $user = User::with(['userBankDetails', 'userDocuments'])
            ->where('id', $id)
            ->where('role', 'USER')
            ->first();

        if (!$user) {
            return response()->json([
                'status_code' => 2,
                'message' => 'User not found or user is not a USER.'
            ]);
        }

        $user = User::with(['userBankDetails', 'userDocuments'])
            ->find($id);

        if (!$user) {
            return response()->json([
                'status_code' => 2,
                'message' => 'User not found.'
            ]);
        }

        $userBankDetails = UserBankDetails::where('user_id', $id)->first();
        $userDocuments = UserDocuments::where('user_id', $id)->get();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'user' => $user,
                'bank_details' => $userBankDetails,
                'documents' => $userDocuments,
                'message' => 'User details fetched successfully.'
            ]

        ]);
    }
    public function changeVerificationStatus(Request $request)
    {
        // Validate the request inputs
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'status_message' => 'nullable|string',
            'status' => 'string',
        ]);

        // Find the user by ID
        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'status_code' => 2,
                'message' => 'User not found.'
            ]);
        }
        $user->doc_status = $request->status;
        // Toggle the user's doc_status
        // if ($user->doc_status === 'PENDING') {
        //     $user->doc_status = 'VERIFIED';
        // } elseif ($user->doc_status === 'VERIFIED') {
        //     $user->doc_status = 'UNVERIFIED';
        // } elseif ($user->doc_status === 'UNVERIFIED') {
        //     $user->doc_status = 'VERIFIED';
        // }

        // Optionally, store the status message if you have a column for it
        $user->status_message = $request->status_message; // Ensure the `status_message` column exists in the users table
        $user->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'User document status updated to ' . $user->doc_status . '.',
            'status_message' => $user->status_message
        ]);
    }


    // public function verifyUser($id)
    // {
    //     $user = User::where('id', $id)
    //         ->where('status', 'VERIFICATIONPENDING')
    //         ->first();

    //     if (!$user) {
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'User not found or user is not pending verification.'
    //         ]);
    //     }

    //     $user->status = 'ACTIVE';
    //     $user->save();

    //     return response()->json([
    //         'status_code' => 1,
    //         'message' => 'User successfully verified and status updated to ACTIVE.'
    //     ]);
    // }
    public function toggleUserStatus(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'user_id' => 'required|exists:users,id', // Ensure the user exists
            'status_message' => 'nullable|string',   // Optional status message
        ]);

        // Find the user by ID from the request
        $user = User::find($request->input('user_id'));

        // Toggle the user's status
        if ($user->status === 'ACTIVE') {
            $user->status = 'INACTIVE';
        } else {
            $user->status = 'ACTIVE';
        }

        // Update the status message if provided
        if ($request->has('status_message')) {
            $user->status_message = $request->input('status_message');
        }

        // Save the updated user data
        $user->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'User status updated to ' . $user->status . '.',
            'status_message' => $user->status_message,
        ]);
    }


}
