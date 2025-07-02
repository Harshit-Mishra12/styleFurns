<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Basic User Info
            $table->string('name');
            $table->string('email')->unique();
            $table->string('mobile')->unique();
            $table->string('password');
            $table->string('profile_picture')->nullable();
            $table->string('status')->default('active'); //active,inactive
            // Flexible Role
            $table->string('role')->default('user'); // e.g., admin, user, technician
            $table->string('job_status')->default('offline'); // e.g., online, offline, engaged
            // OTP System
            $table->string('otp')->nullable();
            $table->string('verification_uid')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->boolean('is_verified')->default(false);

            // Laravel built-in support
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
