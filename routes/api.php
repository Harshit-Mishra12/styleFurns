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
use App\Http\Controllers\V1\Admin\CustomerController;
use App\Http\Controllers\V1\Admin\SkillController;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\TechnicianController;
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
    Route::post("/auth/forget_password", [AuthController::class, 'forgetPassword']);
    Route::post("/auth/forget_password/verify", [AuthController::class, 'forgetPasswordVerifyUser']);
    Route::post("/auth/forget_password/change_password", [AuthController::class, 'forgetPasswordChangePassword']);

    Route::get("/skills/fetch", [SkillController::class, 'index']);
    Route::get('/fetchTermsAndConditions', [TermsAndConditionController::class, 'fetchTermsAndConditions']);
    Route::middleware('auth:sanctum')->post('/bookings/{booking_id}/assign-technician', [AdminBookingController::class, 'assignNearestTechnician']);
    Route::middleware('auth:sanctum')->post('/bookings/{booking_id}/parts/add', [BookingPartsController::class, 'addMissingParts']);
    Route::middleware('auth:sanctum')->post('/bookings/{booking_id}/parts/delete', [BookingPartsController::class, 'deleteBookingPart']);


    Route::middleware('auth:sanctum')->put('/bookings/{booking_id}/parts/{part_id}/update', [BookingPartsController::class, 'update']);
    Route::middleware('auth:sanctum')->get('/bookings/{booking_id}/parts', [BookingPartsController::class, 'index']);
    Route::middleware('auth:sanctum')->get('/technicians/fetch', [TechnicianController::class, 'index']);

    Route::middleware('auth:sanctum')->post('/bookings/status/update', [AdminBookingController::class, 'updateBookingStatus']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('/save-push-token', [NotificationController::class, 'store']);
    });


    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('admin')->group(function () {
            Route::post('/bookings/create', [AdminBookingController::class, 'store']);
            Route::get('/bookings/fetch', [AdminBookingController::class, 'index']);
            Route::get('/bookings/fetch/{id}', [AdminBookingController::class, 'show']);
            Route::post('/bookings/{booking_id}/update', [AdminBookingController::class, 'updateBooking']);
            Route::post('/available_slots/fetch', [AdminBookingController::class, 'getAvailableSlots']);
            Route::get('/bookings/stats', [AdminBookingController::class, 'getStats']);
            Route::post('/bookings/{booking_id}/status/update', [AdminBookingController::class, 'assignTechnicianToRescheduledBooking']);
            Route::get('/customers/fetch', [CustomerController::class, 'index']);


            Route::post('/technicians/work_status/update', [AdminBookingController::class, 'updateTechnicianWorkStatus']);







            // Transaction Handling (Admin can view all transactions)

        });

        Route::prefix('user')->group(function () {

            Route::post('/technician/status', [TechnicianProfileController::class, 'updateJobStatus']);
            Route::post('/technician/location', [TechnicianProfileController::class, 'updateLocation']);
            Route::post('/bookings/{booking_id}/assignment/status', [UserBookingController::class, 'updateAssignmentStatus']);
            Route::post('/technician/update-profile', [TechnicianProfileController::class, 'updateProfile']);
            Route::get('/technician/profile/fetch', [TechnicianProfileController::class, 'getProfile']);
            Route::get('/technician/journey', [TechnicianProfileController::class, 'getJourney']);
            Route::post('/bookings/{booking_id}/images/upload', [UserBookingController::class, 'uploadBookingImages']);
            Route::post('/bookings/{booking_id}/images/delete', [UserBookingController::class, 'deleteBookingImage']);


            Route::post('/technician/booking_assignments/{id}/update_status', [UserBookingController::class, 'updateJobStatus']);
        });

        Route::prefix('retailer')->group(function () {});
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
