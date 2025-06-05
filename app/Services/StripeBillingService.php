<?php

namespace App\Services;

use Exception;
use Stripe\Stripe;
use Stripe\Invoice;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeBillingService
{
    public function __construct()
    {
        Stripe::setApiKey(config('app.stripe_secret'));
    }

    public function createSession($qty,$email)
    {
        $baseUrl = url('/');
        $checkout_session = \Stripe\Checkout\Session::create([
            'customer_email' => $email,
            'line_items' => [[
              'price' => 'price_1OgjuEH8GTBeCWthkvUf6ox8',
              'quantity' => $qty,
            ]],
            'mode' => 'subscription',
            'success_url' => $baseUrl . '/payment/success',
            'cancel_url' => $baseUrl . '/payment/cancel',
          ]);
          return $checkout_session;
    }
    public function getCustomerEmail($customerId)
    {
        try {
            // Retrieve the customer object from Stripe
            $customer = Customer::retrieve($customerId);

            // Extract and return the email from the customer object
            return $customer->email;
        } catch (Exception $e) {
            // Handle any Stripe API errors
            return response()->json(['error' => 'Failed to retrieve customer email'], 500);
        }
    }
    public function verifyWebhookResponse($payload,$sigHeader)
    {
        try {
            // Verify the signature
            $endpointSecret = config('app.stripe_webhook_secret');
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            return $event;
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return -1;
        }
    }
    public function updateSubscriptionQuantity($subscriptionId, $newQuantity)
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);
            $subscription->quantity = $newQuantity;
            $subscription->save();

            return 1;
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    public function cancelSubscription($subscriptionId)
    {
        try {
            $subscription = \Stripe\Subscription::retrieve($subscriptionId);
            $canceledSubscription = $subscription->cancel();
            return true;
        
        } catch (Exception $e) {
           // echo 'Error canceling subscription: ' . $e->getMessage();
            return false;
            
        }
    }
    public function createCustomer($email)
    {
        return Customer::create([
            'email' => $email,
        ]);
    }

    // public function createSubscription($customerId, $priceId)
    // {
    //     return Customer::update($customerId, [
    //         'invoice_settings' => [
    //             'default_payment_method' => 'your_payment_method_id', // Set the default payment method
    //         ],
    //     ])->subscriptions->create(['items' => [['price' => $priceId]]]);
    // }

    public function retrieveSubscription($subscriptionId)
    {
        return Invoice::upcoming(['subscription' => $subscriptionId]);
    }
}

?>