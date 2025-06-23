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
    public function up()
    {
        //
        Schema::create('booking_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // technician
            $table->string('status'); // 'assigned', 'arrived_location','leaving_location','in_progress'
            $table->text('reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            // 🆕 Slot information
            $table->date('slot_date')->nullable();         // e.g. 2025-06-17
            $table->time('time_start')->nullable();        // e.g. 10:00
            $table->time('time_end')->nullable();          // e.g. 13:00

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
