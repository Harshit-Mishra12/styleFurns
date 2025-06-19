<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}
