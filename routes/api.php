<?php


use App\Http\Controllers\V1\Admin\PredictionQuestionController;
use App\Http\Controllers\V1\Admin\SubscriptionController;
use App\Http\Controllers\V1\Admin\UserPredictionMessageController;
use App\Http\Controllers\V1\Admin\TermsAndConditionController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\User\WalletController;
use App\Http\Controllers\V1\Admin\TransactionController;
use App\Http\Controllers\V1\BookingPartsController;
use App\Http\Controllers\V1\User\TechnicianProfileController;
use App\Http\Controllers\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\V1\User\BookingController as UserBookingController;
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

    Route::get('/fetchTermsAndConditions', [TermsAndConditionController::class, 'fetchTermsAndConditions']);
    Route::middleware('auth:sanctum')->post('/bookings/{booking_id}/assign-technician', [AdminBookingController::class, 'assignNearestTechnician']);
    Route::middleware('auth:sanctum')->post('/bookings/{booking_id}/parts/add', [BookingPartsController::class, 'addMissingParts']);

    Route::middleware('auth:sanctum')->put('/bookings/{booking_id}/parts/{part_id}/update', [BookingPartsController::class, 'update']);
    Route::middleware('auth:sanctum')->get('/bookings/{booking_id}/parts', [BookingPartsController::class, 'index']);


    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('admin')->group(function () {
            Route::post('/bookings/create', [AdminBookingController::class, 'store']);
            Route::get('/bookings/fetch', [AdminBookingController::class, 'index']);
            Route::get('/bookings/fetch/{id}', [AdminBookingController::class, 'show']);
            Route::post('/bookings/{booking_id}/update', [AdminBookingController::class, 'update']);
            // Transaction Handling (Admin can view all transactions)

        });

        Route::prefix('user')->group(function () {

            Route::post('/technician/status', [TechnicianProfileController::class, 'updateJobStatus']);
            Route::post('/technician/location', [TechnicianProfileController::class, 'updateLocation']);
            Route::post('/bookings/{booking_id}/assignment/status', [UserBookingController::class, 'updateAssignmentStatus']);

            Route::post('/bookings/{booking_id}/images/upload', [UserBookingController::class, 'uploadBookingImages']);
        });

        Route::prefix('retailer')->group(function () {});
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
