<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Adjust if you want a different email
        $email = 'admin@stylefurns.ca';

        User::updateOrCreate(
            ['mobile' => '6123456789'],  // lookup by mobile (unique), could also include email
            [
                'name'              => 'StyleFurns Admin',
                'email'             => $email,
                'password'          => Hash::make('12345678'),
                'profile_picture'   => null,
                'status'            => 'active',
                'role'              => 'admin',
                'job_status'        => 'online',      // or 'offline' if you prefer
                'otp'               => null,
                'verification_uid'  => Str::uuid()->toString(),
                'otp_expires_at'    => null,
                'is_verified'       => true,          // mark verified so login flows work
                'remember_token'    => Str::random(60),
            ]
        );
    }
}
