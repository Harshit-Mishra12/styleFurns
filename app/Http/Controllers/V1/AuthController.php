<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate input for email or mobile number and password
        $request->validate([
            'email_or_mobile' => 'required',
            'password' => 'required',
        ]);

        // Determine if input is an email or mobile number
        $isEmail = filter_var($request->input('email_or_mobile'), FILTER_VALIDATE_EMAIL);
        $user = $isEmail
            ? User::where('email', $request->input('email_or_mobile'))->first()
            : User::where('mobile', $request->input('email_or_mobile'))->first();

        // Check if user exists
        if (!$user) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Account not registered.',
            ]);
        }

        // Verify the password
        if (!Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Incorrect password.',
            ]);
        }

        // Check if user account is active and verified
        if ($user->status !== 'active') {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Your account is inactive. Please contact support.',
            ]);
        }

        if (!$user->is_verified) {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Your account is not verified yet. Please complete verification or contact support.',
            ]);
        }


        // Generate API token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status_code' => 1,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'role' => $user->role,
                    'profile_picture' => $user->profile_picture,
                    'status' => $user->status,
                    'doc_status' => $user->doc_status,
                    'is_verified' => $user->is_verified, // include in response
                ],
                'token' => $token,
            ],
            'message' => 'Login successful.',
        ]);
    }




    public function register(Request $request)
    {

        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email',
            'mobile'          => 'required|digits:10',
            'password'        => 'required|min:6|confirmed',
            'profile_picture' => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
            'role'            => 'nullable|string',
        ]);



        // Check existing user by mobile or email
        $existingUser = User::where(function ($query) use ($request) {
            $query->where('mobile', $request->mobile)
                ->orWhere('email', $request->email);
        })->first();

        // If user is verified, block registration
        if ($existingUser && $existingUser->is_verified) {
            return response()->json([
                'status_code' => 2,
                'message' => 'User already registered. Please login.',
            ]);
        }

        // Upload profile picture
        $profilePicturePath = null;
        if ($request->hasFile('profile_picture')) {
            $profilePicturePath = Helper::saveImageToServer($request->file('profile_picture'), 'uploads/profile/');
        }

        // Generate random 4-digit OTP and expiry (e.g., 10 minutes)
        $otp = random_int(1000, 9999);
        $otpExpiresAt = Carbon::now()->addMinutes(10);

        if ($existingUser) {
            // Overwrite existing non-verified user
            $existingUser->name            = $request->name;
            $existingUser->email           = $request->email;
            $existingUser->mobile          = $request->mobile;
            $existingUser->password        = bcrypt($request->password);
            $existingUser->profile_picture = $profilePicturePath;
            $existingUser->role            = $request->role ?? 'user';
            $existingUser->otp             = $otp;
            $existingUser->otp_expires_at  = $otpExpiresAt;
            $existingUser->is_verified     = false;
            $existingUser->save();

            return response()->json([
                'status_code' => 1,
                'message'     => 'User registered successfully. Please verify OTP.',
                'otp'         => $otp,   // For testing, remove in production
                'data'        => ['id' => $existingUser->id],
            ]);
        }

        // Create new user with OTP
        $user = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'mobile'          => $request->mobile,
            'password'        => bcrypt($request->password),
            'profile_picture' => $profilePicturePath,
            'role'            => $request->role ?? 'user',
            'otp'             => $otp,
            'status'          => 'active',
            'otp_expires_at'  => $otpExpiresAt,
            'is_verified'     => false,
        ]);

        return response()->json([
            'status_code' => 1,
            'message'     => 'User registered successfully. Please verify OTP.',
            'otp'         => $otp,   // For testing, remove in production
            'data'        => ['id' => $user->id],
        ]);
    }



    private function sendOtpSMS($mobileNumber, $otp)
    {
        $fields = array(
            "message" => "Your OTP for registration is: $otp",
            "language" => "english",
            "route" => "q",
            "numbers" => $mobileNumber,
        );

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => array(
                "authorization: 1A5KFGtiU27gVQfnch8oZsjpauSBxvY0blTCDedJXHEk9ILPOmLiUSjEIoOgtM03yG1XZQHrWpsTucCB", // Your API key
                "accept: */*",
                "cache-control: no-cache",
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        return json_decode($response, true);
    }


    public function verifyUser(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'otp' => 'required'
        ]);

        $user = User::find($request->input('id'));
        if (!$user || $user->is_verified == 1) {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not found']);
        }
        if ($user->otp == $request->input('otp')) {

            $user->update([
                'is_verified' => true,
                'otp' => '',
                'status' => 'active'
            ]);
            $token = $user->createToken('api-token')->plainTextToken;
            // $this->createSubscription($user->id);
            return response()->json(['status_code' => 1, 'data' => ['user' => $user, 'token' => $token], 'message' => 'User verified successfully']);
        }
        return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Invalid Otp']);
    }





    public function forgetPassword(Request $request)
    {
        $request->validate([
            'mobile' => 'required',
        ]);

        // Fetch the user by mobile
        $user = User::where('mobile', $request->mobile)->first();
        $otp = mt_rand(1000, 9999);

        if ($user) {
            // $this->sendOtpSMS($request->mobile, $otp);
            $user->update([
                'otp' => $otp,

            ]);
            // Send OTP to the user's email
            $data = [
                'name' => $user->name,
                'otp' => $otp
            ];
            $body = view('email.otp_verification', $data)->render();
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $subject = 'Verify your email';
            // Helper::sendEmail($user->email, $subject, $body, $headers);

            return response()->json(['status_code' => 1, 'data' => ['id' => $user->id], 'message' => 'OTP has been sent to your registered email address or mobile number. You can later change your password.', 'otp' => $otp]);
        } else {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not registered.']);
        }
    }

    public function forgetPasswordVerifyUser(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'otp' => 'required'
        ]);

        $user = User::find($request->input('id'));

        if (!$user) {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not found']);
        }
        if ($user->otp == $request->input('otp')) {
            $uid = Str::uuid()->toString();
            $user->update([
                'otp' => '',
                'verification_uid' => $uid
            ]);

            return response()->json(['status_code' => 1, 'data' => ['id' => $user->id, 'uid' => $uid], 'message' => 'Email verified. Continue to change your password']);
        }
        return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Invalid Otp']);
    }


    public function forgetPasswordChangePassword(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'password' => 'required',
            'verification_uid' => 'required|string'
        ]);

        $user = User::where('id', $request->input('id'))
            ->where('verification_uid', $request->input('verification_uid'))
            ->first();

        if (!$user) {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not found']);
        }

        $user->update([
            'password' =>  bcrypt($request->input('password')),
            'verification_uid' => ''
        ]);

        return response()->json(['status_code' => 1, 'data' => [], 'message' => 'Password changed.']);
    }
    public function meProfile()
    {
        return response()->json(['status_code' => 1, 'data' => [auth()->user()], 'message' => 'User profile fetched successfully']);
    }




    public function createContact(Request $request)
    {

        // Validate incoming request data
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'contact' => 'required|string',
            'type' => 'required|string',
            'reference_id' => 'nullable|string',
            'notes' => 'nullable|array',
        ]);

        // Step 1: Create a Contact
        $contactResponse = Http::withBasicAuth(
            Helper::getRazorpayKeyId(),
            Helper::getRazorpayKeySecret()
        )
            ->post('https://api.razorpay.com/v1/contacts', [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'contact' => $request->input('contact'),
                'type' => $request->input('type'),
                'reference_id' => $request->input('reference_id'),
                'notes' => $request->input('notes', []),
            ]);

        // Check if the contact creation was successful
        if ($contactResponse->failed()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Failed to create contact.',
                'error' => $contactResponse->json(),
            ]);
        }

        // If successful, return the contact details
        return response()->json([
            'status_code' => 1,
            'message' => 'Contact created successfully.',
            'contact' => $contactResponse->json(),
        ]);
    }
    public function createFundAccount(Request $request)
    {
        $request->validate([
            'contact_id' => 'required|string',
            'name' => 'required|string',
            'ifsc' => 'required|string',
            'account_number' => 'required|string',
        ]);

        try {
            // Make API request using Http facade with Basic Auth
            $response = Http::withBasicAuth(
                Helper::getRazorpayKeyId(),
                Helper::getRazorpayKeySecret()
            )->post('https://api.razorpay.com/v1/fund_accounts', [
                'contact_id' => $request->input('contact_id'),
                'account_type' => 'bank_account',
                'bank_account' => [
                    'name' => $request->input('name'),
                    'ifsc' => $request->input('ifsc'),
                    'account_number' => $request->input('account_number'),
                ],
            ]);

            // Handle response
            if ($response->successful()) {
                return response()->json([
                    'status_code' => 1,
                    'message' => 'Fund account created successfully',
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Error creating fund account',
                    'error' => $response->json(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Error occurred while processing the request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validateFundAccount(Request $request)
    {
        $request->validate([
            'account_number' => 'required|string',
            'fund_account_id' => 'required|string',
            'amount' => 'required|integer',
            'currency' => 'required|string|in:INR',
            'notes' => 'nullable|array'
        ]);

        try {
            // Make API request using Http facade with Basic Auth
            $response = Http::withBasicAuth(
                Helper::getRazorpayKeyId(),
                Helper::getRazorpayKeySecret()
            )->post('https://api.razorpay.com/v1/fund_accounts/validations', [
                'account_number' => $request->input('account_number'),
                'fund_account' => [
                    'id' => $request->input('fund_account_id'),
                ],
                'amount' => $request->input('amount'),
                'currency' => $request->input('currency'),
                'notes' => $request->input('notes', [])
            ]);

            // Handle response
            if ($response->successful()) {
                return response()->json([
                    'status_code' => 1,
                    'message' => 'Fund account validated successfully',
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Error validating fund account',
                    'error' => $response->json(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Error occurred while processing the request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
