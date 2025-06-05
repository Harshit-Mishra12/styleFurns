<?php
namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller; // ✅ Import the base Controller
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\UserSubscription;

class SubscriptionController extends Controller {
    /**
     * Get all subscription plans
     */
    public function index() {
        $subscriptions = Subscription::all();

        return response()->json([
            'status_code' => 1,
            'message' => 'Subscriptions fetched successfully',
            'data' => $subscriptions
        ]);
    }

    /**
     * Store a new subscription plan
     */
    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'pricing' => 'required|numeric',
            'description' => 'required|string',
            'reward_type' => 'required|in:match_credit,days_credit',
            'reward_value' => 'required|integer',
        ]);

        $subscription = Subscription::create($request->all());

        return response()->json([
            'status_code' => 1,
            'message' => 'Subscription created successfully',
            'data' => $subscription
        ]);
    }

    /**
     * Update a subscription plan
     */
    public function update(Request $request, $id) {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Subscription not found'
            ]);
        }

        $subscription->update($request->all());

        return response()->json([
            'status_code' => 1,
            'message' => 'Subscription updated successfully',
            'data' => $subscription
        ], 200);
    }

    /**
     * Delete a subscription plan
     */
    public function destroy($id) {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Subscription not found'
            ]);
        }

        $subscription->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Subscription deleted successfully'
        ], 200);
    }

    public function checkSubscriptionAndDeductCredits($user_id) {
        $userSubscription = UserSubscription::where('user_id', $user_id)
            ->orderByDesc('created_at')
            ->first(); // Get latest subscription

        if (!$userSubscription) {
            return response()->json([
                'status_code' => 2,
                'message' => 'No active subscription found'
            ], 400);
        }

        // 1️⃣ **Check if Days Credit is Available**
        if ($userSubscription->reward_type === 'days_credit' && $userSubscription->days_remaining > 0) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Days credit is active',
                'days_remaining' => $userSubscription->days_remaining
            ]);
        }

        // 2️⃣ **If Days Credit is Finished, Use Match Credits**
        if ($userSubscription->reward_type === 'match_credit' && $userSubscription->match_credits > 0) {
            // Deduct one match credit
            $userSubscription->match_credits -= 1;
            $userSubscription->save();

            return response()->json([
                'status_code' => 1,
                'message' => 'Match credit used, prediction allowed',
                'match_credits_remaining' => $userSubscription->match_credits
            ]);
        }

        // 3️⃣ **If Both Credits Are Finished**
        return response()->json([
            'status_code' => 2,
            'message' => 'No credits available. Please subscribe to continue.'
        ], 400);
    }
}


