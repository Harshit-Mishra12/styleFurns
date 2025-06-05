<?php

namespace App\Http\Controllers\V1\User;


use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBankDetails;
use App\Models\UserDocuments;
use App\Models\UserWallet;
use App\Helpers\Helper;
use Exception;
use Illuminate\Http\Request;



class ProfileController extends Controller
{


    public function fetchUserProfileDetails()
    {
        // Get the currently authenticated user
        $user = auth()->user();

        // Check if the user exists and their role is 'USER'
        if (!$user || $user->role !== 'USER') {
            return response()->json([
                'status_code' => 2,
                'message' => 'User not found or user is not a USER.'
            ]);
        }

        // Fetch user bank details and documents
        $userBankDetails = UserBankDetails::where('user_id', $user->id)->first();
        $userDocuments = UserDocuments::where('user_id', $user->id)->get();

        // Fetch user wallet balance
        $userWallet = UserWallet::where('user_id', $user->id)->first();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'user' => $user,
                'bank_details' => $userBankDetails,
                'documents' => $userDocuments,
                'wallet_balance' => $userWallet ? $userWallet->balance : 0, // Ensure default value if wallet not found
            ],
            'message' => 'User details fetched successfully.'
        ]);
    }



    public function updateProfile(Request $request)
    {
        // Validate the request
        $request->validate([
            'name' => 'nullable|string|max:255', // Optional name input
            'profile_picture' => 'nullable|mimes:jpeg,jpg,png|max:2048', // Optional profile picture
            'documents_type' => 'nullable|array', // Optional documents
            'documents_type.*' => 'nullable|string',
            'documents_picture' => 'nullable|array', // Optional document files
            'documents_picture.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'dob' => 'nullable|string|', // Optional name input
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json(['status_code' => 2, 'message' => 'User not found.']);
        }

        // Handle profile picture and name updates
        $updateData = [];

        // Check if name is provided and update
        if ($request->has('name')) {
            $updateData['name'] = $request->input('name');
        }

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $profilePicturePath = Helper::saveImageToServer($file, 'uploads/profile/');
            $updateData['profile_picture'] = $profilePicturePath;
        }
        if ($request->has('dob')) {

            $updateData['dob'] = $request->input('dob');
        }


        // Update user details
        if (!empty($updateData)) {
            User::where('id', $user->id)->update($updateData);
        }

        // Handle document updates using UserDocuments model
        $documentTypes = $request->input('documents_type', []); // Default to empty array
        $documentPictures = $request->file('documents_picture', []); // Default to empty array

        if (count($documentTypes) != count($documentPictures)) {
            return response()->json(['status_code' => 2, 'message' => 'Mismatch between document types and pictures.']);
        }
        if (!empty($documentTypes)) {
            // If documents_type is provided, update the user's doc_status to PENDING using the update query
            User::where('id', $user->id)->update(['doc_status' => 'PENDING']);
        }
        foreach ($documentPictures as $index => $documentFile) {
            $docType = $documentTypes[$index];

            if ($documentFile instanceof \Illuminate\Http\UploadedFile) {
                // Save the document file
                $docUrlPath = Helper::saveImageToServer($documentFile, 'uploads/documents/');

                // Check if a document with the same type already exists using UserDocuments
                $existingDocument = UserDocuments::where('user_id', $user->id)
                    ->where('doc_type', $docType)
                    ->first();

                if ($existingDocument) {
                    // Update the existing document
                    $existingDocument->update([
                        'doc_url' => $docUrlPath
                    ]);
                } else {
                    // Create a new document record if it doesn't exist
                    UserDocuments::create([
                        'user_id' => $user->id,
                        'doc_type' => $docType,
                        'doc_url' => $docUrlPath
                    ]);
                }
            } else {
                return response()->json(['status_code' => 2, 'message' => 'Invalid document file.']);
            }
        }

        return response()->json(['status_code' => 1, 'message' => 'Profile updated successfully.']);
    }

    public function updateBankDetails(Request $request)
    {
        // Validate incoming request
        $validated = $request->validate([
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:20',
            'ifsc_code' => 'required|string|max:11',
        ]);

        // Get the authenticated user
        $user = auth()->user();

        // Check if bank details already exist for the user
        $bankDetails = UserBankDetails::where('user_id', $user->id)->first();

        if (!$bankDetails) {
            // If not, create a new entry
            $bankDetails = new UserBankDetails();
            $bankDetails->user_id = $user->id;
        }

        // Update bank details
        $bankDetails->account_name = $validated['account_name'];
        $bankDetails->account_number = $validated['account_number'];
        $bankDetails->ifsc_code = $validated['ifsc_code'];
        $bankDetails->save();
        User::where('id', $user->id)->update(['is_bank_details_verified' => true]);
        return response()->json([
            'status_code' => 1,
            'message' => 'Bank details updated and verified successfully.',
        ], 200);
    }
}
