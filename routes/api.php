<?php

use App\Http\Controllers\V1\Admin\PredictionQuestionController;
use App\Http\Controllers\V1\Admin\SubscriptionController;
use App\Http\Controllers\V1\Admin\UserPredictionMessageController;
use App\Http\Controllers\V1\Admin\TermsAndConditionController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\User\WalletController;
use App\Http\Controllers\V1\Admin\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::prefix('v1')->group(function () {

    Route::get('/config-clear', function () {
        Artisan::call('config:clear');
        return response()->json(['message' => 'Config cache cleared successfully.']);
    })->name('config.clear');
    Route::get('/optimize', function () {
        Artisan::call('optimize');
        return response()->json(['message' => 'Application optimized successfully.']);
    })->name('optimize');

    Route::post("/auth/login", [AuthController::class, 'login']);
    Route::post("/auth/register", [AuthController::class, 'register']);
    Route::post("/auth/register2", [AuthController::class, 'register2']);
    Route::post("/auth/register/verify", [AuthController::class, 'verifyUser']);
    Route::post("/auth/forget-password", [AuthController::class, 'forgetPassword']);
    Route::post("/auth/forget-password/verify", [AuthController::class, 'forgetPasswordVerifyUser']);
    Route::post("/auth/forget-password/change-password", [AuthController::class, 'forgetPasswordChangePassword']);
    Route::post("/auth/bank-account/verify", [AuthController::class, 'verifyBankAccount']);
    Route::post('/wallet/verify-payment', [WalletController::class, 'verifyPayment']);
    Route::get('/fetchTermsAndConditions', [TermsAndConditionController::class, 'fetchTermsAndConditions']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('admin')->group(function () {
            Route::post('/subscriptions', [SubscriptionController::class, 'store']);
            Route::put('/subscriptions/{id}', [SubscriptionController::class, 'update']);
            Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'destroy']);
            Route::get('/subscriptions', [SubscriptionController::class, 'index']);


            Route::post('/predictions/questions', [PredictionQuestionController::class, 'store']); // Create
            Route::put('/predictions/questions/{id}', [PredictionQuestionController::class, 'update']); // Update
            Route::delete('/predictions/questions/{id}', [PredictionQuestionController::class, 'destroy']); // Delete
            Route::get('/predictions/questions', [PredictionQuestionController::class, 'index']); // Create



            Route::get('/predictions/messages', [UserPredictionMessageController::class, 'index']);
            Route::put('/predictions/messages/{id}', [UserPredictionMessageController::class, 'updateResponse']);
            Route::delete('/predictions/messages/{id}', [UserPredictionMessageController::class, 'destroy']);



             // Transaction Handling (Admin can view all transactions)
             Route::get('/transactions', [TransactionController::class, 'index']);
        });

        Route::prefix('user')->group(function () {


            Route::post('/predictions/messages', [UserPredictionMessageController::class, 'store']);
        });

        Route::prefix('retailer')->group(function () {});
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
