<?php

namespace App\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\User;
use Carbon\Carbon;

class SkillController extends Controller
{
    public function index()
    {
        return response()->json([
            'status_code' => 1,
            'data' => Skill::all(),
            'message' => 'Skills fetched successfully.',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'skills' => 'required|array',
            'skills.*' => 'required|string',
        ]);

        foreach ($request->skills as $skillName) {
            Skill::firstOrCreate(['name' => $skillName]);
        }

        return response()->json(['status_code' => 1, 'message' => 'Skills created successfully.']);
    }
}
