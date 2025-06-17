<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Skill;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            "Leather Repair",
            "Wood Touch-ups",
            "Stitching (Repair of Seams)",
            "Structure Repair",
            "Mechanism Repair",
            "Foam Change / Addition",
            "Foam Deformation Repair",
            "Part Replacement",
            "Fabric Cleaning",
            "Leather Cleaning",
            "Recliner Adjustment",
            "Frame Tightening",
            "Spring Repair",
            "Upholstery Re-attachment"
        ];

        foreach ($skills as $skill) {
            Skill::firstOrCreate(['name' => $skill]);
        }
    }
}
