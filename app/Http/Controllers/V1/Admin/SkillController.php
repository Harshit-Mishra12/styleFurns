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

    public function registerWithSkills(Request $request)
    {
        $request->validate([
            'name'     => 'required|string',
            'email'    => 'required|email',
            'mobile'   => 'required|digits:10',
            'password' => 'required|string|min:6|confirmed',
            'technician_skills' => 'required|array',
            'technician_skills.*' => 'integer|exists:skills,id',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'mobile'   => $request->mobile,
            'password' => bcrypt($request->password),
            'role'     => 'technician',
            'otp'      => rand(1000, 9999),
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $user->skills()->attach($request->technician_skills);

        return response()->json([
            'status_code' => 1,
            'message' => 'Technician registered successfully with skills.',
            'data' => ['id' => $user->id],
        ]);
    }
}
