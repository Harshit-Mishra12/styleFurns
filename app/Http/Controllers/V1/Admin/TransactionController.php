<?php

namespace App\Http\Controllers\V1\Admin;


use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\User;
use App\Models\UserBankDetails;
use App\Models\UserTransaction;
use App\Models\UserWallet;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;



class TransactionController extends Controller
{
    public function getAllUserTransactions(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'per_page' => 'integer|min:1',       // Pagination: items per page
            'page' => 'integer|min:1',           // Pagination: page number
            'transaction_id' => 'string|nullable', // Filter by transaction number
            'date' => 'date|nullable|date_format:Y-m-d', // Filter by created_at date
        ]);

        // Define the number of items per page, default is 10
        $perPage = $validated['per_page'] ?? 10;

        // Base query for user transactions
        $query = UserTransaction::query();

        // Apply transaction ID filter if provided
        if (!empty($validated['transaction_id'])) {
            $query->where('transaction_id', $validated['transaction_id']);
        }

        // Apply date filter if provided
        if (!empty($validated['date'])) {
            $query->whereDate('created_at', $validated['date']);
        }

        // Paginate the results
        $transactions = $query->with('user') // Eager load the user relationship to fetch user names
            ->with('userBankDetails') // Load UserBankDetails relationship
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Map the transactions with user details and format the response
        $transactionsList = $transactions->map(function ($transaction) {
            // Get the user's name from the user_id
            $userName = User::where('id', $transaction->user_id)->value('name');

            // Get bank details for the user
            $userBankDetails = UserBankDetails::where('user_id', $transaction->user_id)->first();

            return [
                'id' => $transaction->id,
                'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                'amount' => $transaction->amount,
                'user_name' => $userName,
                'user_id' => $transaction->user_id,
                'transaction_type' => ucfirst($transaction->transaction_type), // Capitalize 'credit' or 'debit'
                'status' => ucfirst($transaction->status), // Capitalize status
                'transaction_id' => $transaction->transaction_id,
                'transaction_usecase' => $transaction->transaction_usecase,
                'bank_details' => [
                    'account_name' => $userBankDetails ? $userBankDetails->account_name : null,
                    'account_number' => $userBankDetails ? $userBankDetails->account_number : null,
                    'ifsc_code' => $userBankDetails ? $userBankDetails->ifsc_code : null,
                ],
            ];
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'User transactions retrieved successfully',
            'data' => $transactionsList,
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
        ]);
    }

    public function updateTransactionStatus(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'id' => 'required|integer|exists:user_transactions,id', // Changed to 'id'
            'user_id' => 'required|integer|exists:users,id',
            ''
        ]);

        // Find the transaction
        $transaction = UserTransaction::where('id', $validated['id']) // Use the primary key
            ->where('user_id', $validated['user_id'])
            ->first();

        // Check if transaction exists
        if (!$transaction) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Transaction not found for the given user.',
            ], 404);
        }

        // Update the transaction status to 'COMPLETED'
        $transaction->status = 'COMPLETED';
        $transaction->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'Transaction status updated to COMPLETED successfully.',
            'data' => $transaction,
        ]);
    }

    public function rejectTransactionStatus(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'id' => 'required|integer|exists:user_transactions,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        // Find the transaction
        $transaction = UserTransaction::where('id', $validated['id']) // Use the primary key
            ->where('user_id', $validated['user_id'])
            ->first();

        // Check if transaction exists
        if (!$transaction) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Transaction not found for the given user.',
            ], 404);
        }

        // Update the transaction status to 'REJECTED'
        $transaction->status = 'REJECTED';
        $transaction->save();

        // Retrieve the amount and ensure it's positive
        $amount = abs($transaction->amount); // Ensure amount is always positive

        // Create a new transaction entry for the refund
        UserTransaction::create([
            'user_id' => $validated['user_id'],
            'team_id' => null,
            'amount' => $amount, // Refund amount
            'status' => 'COMPLETED',
            'transaction_type' => 'credit',
            'description' => "Refund the amount back to wallet",
            'transaction_id' => Str::uuid(), // Generate a unique transaction ID
        ]);

        // Update the user's wallet balance
        $userWallet = UserWallet::where('user_id', $validated['user_id'])->first();

        if ($userWallet) {
            $userWallet->balance += $amount; // Add the positive amount to the existing balance
            $userWallet->save(); // Save the updated balance
        } else {
            return response()->json([
                'status_code' => 2,
                'message' => 'User wallet not found.',
                'data' => [],
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Transaction status updated to REJECTED successfully, and amount refunded to wallet.',
            'data' => $transaction,
        ]);
    }

}
