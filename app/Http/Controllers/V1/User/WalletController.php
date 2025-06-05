<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\UserWallet;
use App\Models\UserTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;

class WalletController extends Controller
{
    // Add funds to user wallet (Credit)
    public function createOrder(Request $request)
    {
        $amount = $request->input('amount'); // Amount in paise
        $currency = 'INR';
        $receipt = uniqid();

        // Create order payload
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => $receipt,
            'payment_capture' => 1, // Auto capture
        ];

        // Make HTTP request to Razorpay API
        $response = Http::withBasicAuth(Helper::getRazorpayKeyId(), Helper::getRazorpayKeySecret())
            ->post('https://api.razorpay.com/v1/orders', $payload);

        if ($response->successful()) {
            return response()->json([
                'status_code' => 1,
                'order_id' => $response['id'],
                'message' => 'Order created successfully.',
            ]);
        }

        Log::error('Razorpay Order Error: ' . $response->body());
        return response()->json(['status_code' => 2, 'message' => 'Failed to create order.']);
    }
    public function verifyPayment(Request $request)
    {
        // Log the raw input for debugging
        Log::info('Raw Webhook Payload:', [$request->getContent()]);

        // Extract amount and email from the payload
        $amount = $request->input('payload.payment.entity.amount', null);
        $email = $request->input('payload.payment.entity.email', null);
        $transaction_id = $request->input('payload.payment.entity.id', null);
        $status = $request->input('payload.payment.entity.status', null);
        // Log the extracted values for confirmation
        Log::info('Amount:', [$amount]);
        Log::info('Email:', [$email]);
        Log::info('Id:', [$transaction_id]);
        // Check if amount and email exist
        if ($amount && $email) {
            $user = User::where('email', $email)->first();
            // Process the webhook, e.g., add funds to the user's wallet
            if ($status == 'authorized') {
                $this->addFunds($amount / 100, $user->id, $transaction_id);
            } else {
                Log::error('payment not authorized!.');
            }
        } else {
            Log::error('Missing amount or email in the webhook payload.');
        }

        return response()->json(['status' => 'success']);
    }



    public function addFunds($amount, $user_id, $transaction_id)
    {
        // Validate the input
        if (!is_numeric($amount) || $amount <= 0) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Invalid amount.',
            ]);
        }

        $userId = $user_id; // Get authenticated user ID

        DB::beginTransaction();

        try {
            // Retrieve or create the user's wallet
            $userWallet = UserWallet::firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 0]
            );
            Log::info('amount check:', [$amount]);
            // Add the funds (credit) to the wallet
            $userWallet->balance += $amount;
            $userWallet->save();

            // Record the transaction
            UserTransaction::create([
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_id' => $transaction_id,
                'transaction_type' => 'credit',
                'description' => 'Wallet Top-up',
                'transaction_usecase'=>'topup',
                'status'=>'COMPLETED'
            ]);

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Funds added successfully',
                'balance' => $userWallet->balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to add funds',
                'error' => $e->getMessage(),
            ]);
        }
    }
    public function getUserTransactions()
    {
        $userId = auth()->user()->id;  // Get authenticated user's ID

        // Fetch transactions for the authenticated user
        $transactions = UserTransaction::where('user_id', $userId)
            ->select('user_id', 'team_id', 'amount', 'transaction_type', 'description', 'transaction_id','created_at','status')
            ->orderBy('created_at', 'desc')
            ->get();

        // Check if the user has any transactions
        if ($transactions->isEmpty()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'No transactions found for this user.',
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Transactions retrieved successfully.',
            'data' => $transactions,
        ]);
    }

    // Deduct funds from wallet for team participation (Debit)
    public function participateInTeam(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'team_id' => 'required|integer|exists:teams,id', // Team ID must exist in teams table
            'participation_fee' => 'required|numeric|min:1', // Must be a positive number
        ]);
        // Check if the user already paid for this team

        $userId = auth()->id(); // Get authenticated user ID
        $teamId = $validated['team_id'];
        $participationFee = $validated['participation_fee'];
        $existingTransaction = UserTransaction::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->first();

        if ($existingTransaction) {
            return response()->json([
                'status_code' => 2,
                'message' => 'User has already paid for this team.',
            ]);
        }
        DB::beginTransaction();

        try {
            // Retrieve the user's wallet
            $userWallet = UserWallet::where('user_id', $userId)->first();

            if (!$userWallet || $userWallet->balance < $participationFee) {
                // Insufficient funds
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Insufficient funds in the wallet',
                ]);
            }

            // Deduct the participation fee from the wallet (debit)
            $userWallet->balance -= $participationFee;
            $userWallet->save();

            // Record the transaction
            UserTransaction::create([
                'user_id' => $userId,
                'team_id' => $teamId,
                'amount' => -$participationFee, // Negative amount means debit
                'transaction_type' => 'debit',
                'description' => 'Team Participation',
                'transaction_usecase'=>'participation'
            ]);

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Participation successful',
                'balance' => $userWallet->balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to participate',
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Fetch user's transaction history
    public function getTransactionHistory()
    {
        $userId = auth()->id(); // Get authenticated user ID

        // Get all transactions for the authenticated user
        $transactions = UserTransaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Transaction history retrieved successfully',
            'data' => $transactions,
        ]);
    }


    public function withdrawAmount(Request $request)
    {
        // Get the authenticated user's ID
        $userId = auth()->id();
        // Validate the request data
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01', // Ensure amount is positive
        ]);

        try {
            DB::beginTransaction();

            // Get the user's wallet
            $userWallet = UserWallet::where('user_id', $userId)->first();

            // Check if the user has enough balance to withdraw
            if ($userWallet->balance < $validated['amount']) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Insufficient balance for withdrawal',
                ],200);
            }

            // Generate a unique transaction ID (UUID format or any custom format)
            $transactionId = null;

            // Convert the amount to a negative value for debit (withdrawal)
            $debitAmount = -abs($validated['amount']); // Ensure it's always negative

            // Create a new user transaction for withdrawal
            $transaction = UserTransaction::create([
                'user_id' => $userId, // Automatically assign the authenticated user's ID
                'team_id' => null, // Since this is a withdrawal, team_id is null
                'amount' => $debitAmount, // Store as a negative amount
                'transaction_type' => 'debit', // Withdrawal is a debit
                'description' => 'Amount is withdrawn',
                'transaction_id' => $transactionId,
                'status' => 'PENDING', // Set the status to PENDING
                'transaction_usecase'=>'withdraw'
            ]);

            // Update the user's wallet balance by deducting the withdrawal amount
            $userWallet->balance += $debitAmount; // Subtract the debit amount (negative value)
            $userWallet->save();
            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'status_code' => 1,
                'message' => 'Withdrawal request submitted successfully',
                'data' => [
                    'transaction_id' => $transaction->transaction_id,
                    'status' => $transaction->status,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            // Return error response
            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to process withdrawal request',
                'error' => $e->getMessage(),
            ],200);
        }
    }

}
