<?php
namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PredictionQuestion;

class PredictionQuestionController extends Controller {
    /**
     * Get all prediction questions
     */
    public function index() {
        $questions = PredictionQuestion::all();

        return response()->json([
            'status_code' => 1,
            'message' => 'Prediction questions fetched successfully',
            'data' => $questions
        ]);
    }

    /**
     * Create a new prediction question
     */
    public function store(Request $request) {
        $request->validate([
            'question_text' => 'required|string',

        ]);

        $question = PredictionQuestion::create($request->all());

        return response()->json([
            'status_code' => 1,
            'message' => 'Prediction question created successfully',
            'data' => $question
        ]);
    }

    /**
     * Update an existing prediction question
     */
    public function update(Request $request, $id) {
        $question = PredictionQuestion::find($id);

        if (!$question) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Prediction question not found'
            ]);
        }

        $request->validate([
            'question_text' => 'required|string',
            'status' => 'required|in:active,inactive'
        ]);

        $question->update($request->all());

        return response()->json([
            'status_code' => 1,
            'message' => 'Prediction question updated successfully',
            'data' => $question
        ]);
    }

    /**
     * Delete a prediction question
     */
    public function destroy($id) {
        $question = PredictionQuestion::find($id);

        if (!$question) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Prediction question not found'
            ]);
        }

        $question->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Prediction question deleted successfully'
        ]);
    }
}

