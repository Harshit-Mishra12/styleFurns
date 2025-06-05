
<?php

namespace App\Http\Controllers\V1\User;



use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\UserSubscription;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller {
    public function createOrder(Request $request) {
        $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        $subscription = Subscription::findOrFail($request->subscription_id);
        $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

        // Create Razorpay order
        $orderData = [
            'amount' => $subscription->price * 100, // Convert to paise
            'currency' => 'INR',
            'receipt' => 'order_' . uniqid(),
            'payment_capture' => 1
        ];

        $order = $api->order->create($orderData);

        // Store order in the database
        $userSubscription = UserSubscription::create([
            'user_id' => Auth::id(),
            'subscription_id' => $subscription->id,
            'razorpay_payment_id' => $order['id'],
            'amount_paid' => $subscription->price,
            'status' => 'pending',
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Order created successfully',
            'order_id' => $order['id'],
            'amount' => $order['amount']
        ]);
    }
}
