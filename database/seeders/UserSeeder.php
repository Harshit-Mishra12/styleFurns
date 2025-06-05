<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        $batchSize = 1000; // Number of records per batch
        $users = []; // Array to hold user data temporarily

        foreach (range(1, 100000) as $index) {
            $users[] = [
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'mobile_number' => $faker->phoneNumber,
                'dob' => $faker->date(),
                'password' => bcrypt('password'),
                'profile_picture' => $faker->imageUrl(),
                'is_verified' => true,
                'role' => 'RANDOMUSER',
                'status' => 'ACTIVE',
                'doc_status' => 'PENDING',
                'status_message' => $faker->sentence(),
                'is_bank_details_verified' => $faker->boolean(50),
                'otp' => $faker->randomNumber(6, true),
                'verification_uid' => Str::random(32),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert the batch into the database when it reaches the batch size
            if (count($users) == $batchSize) {
                DB::table('users')->insert($users);
                $users = []; // Reset the array for the next batch
            }
        }

        // Insert any remaining records that didn't complete a batch
        if (!empty($users)) {
            DB::table('users')->insert($users);
        }
    }
}
