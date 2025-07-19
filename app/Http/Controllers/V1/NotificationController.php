<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\UserPushToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     */
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Notifications fetched successfully.',
            'data' => $notifications
        ]);
    }

    /**
     * Mark one or all notifications as read.
     */
    public function markAsRead(Request $request)
    {
        $request->validate([
            'notification_id' => 'nullable|exists:notifications,id'
        ]);

        if ($request->filled('notification_id')) {
            // Mark single notification as read
            $notification = Notification::where('id', $request->notification_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $notification->update(['is_read' => true]);
        } else {
            // Mark all notifications as read
            Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Notification(s) marked as read.'
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $user = $request->user();
        $token = $request->input('token');

        // 1. If this token is already assigned to another user, null it
        UserPushToken::where('device_token', $token)
            ->where('user_id', '!=', $user->id)
            ->update(['device_token' => null]);

        // 2. Update or create current user's token
        $pushToken = UserPushToken::updateOrCreate(
            ['user_id' => $user->id],
            ['device_token' => $token]
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'Push token saved successfully.',
            'data' => $pushToken
        ]);
    }
    public function send(Request $request)
    {

        $data = $request->validate([
            'notification_type' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6])],
            'technician_ids'    => ['required', 'array', 'min:1'],
            'technician_ids.*'  => ['integer', 'exists:users,id'],
        ]);


        Helper::sendPushNotification(
            $data['notification_type'],
            $data['technician_ids']
        );

        return response()->json([
            'status_code' => 1,
            'message'     => 'Test notification dispatched (check device(s) & logs).',
            'data'        => [
                'notification_type' => $data['notification_type'],
                'technician_ids'    => $data['technician_ids']
            ]
        ]);
    }
}
