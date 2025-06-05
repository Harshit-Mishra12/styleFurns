<?php
namespace App\Http\Controllers\V1\Admin;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserPredictionMessage;


class UserPredictionMessageController extends Controller {
    /**
     * Get all user predictions (Admin only)
     */
    public function index() {
        $messages = UserPredictionMessage::with(['user', 'match', 'question'])->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'User predictions fetched successfully',
            'data' => $messages
        ]);
    }

    /**
     * Store a new user prediction (User)
     */
    public function store(Request $request) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'match_id' => 'required|exists:matches,id',
            'question_id' => 'required|exists:prediction_questions,id',
        ]);

        $message = UserPredictionMessage::create([
            'user_id' => $request->user_id,
            'match_id' => $request->match_id,
            'question_id' => $request->question_id,
            'admin_reply' => null // Default to null until admin replies
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Prediction message submitted successfully',
            'data' => $message
        ]);
    }

    /**
     * Admin responds to a user prediction message
     */
    public function updateResponse(Request $request, $id) {
        $message = UserPredictionMessage::find($id);

        if (!$message) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Prediction message not found'
            ]);
        }

        $request->validate([
            'admin_reply' => 'required|string'
        ]);

        $message->update([
            'admin_reply' => $request->admin_reply
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Admin response updated successfully',
            'data' => $message
        ]);
    }

    /**
     * Delete a prediction message (Admin)
     */
    public function destroy($id) {
        $message = UserPredictionMessage::find($id);

        if (!$message) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Prediction message not found'
            ]);
        }

        $message->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Prediction message deleted successfully'
        ]);
    }
}
