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
        Schema::table('booking_assignments', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('slot_date');
            $table->timestamp('ended_at')->nullable()->after('started_at');
            $table->string('current_job_status')->nullable()->after('ended_at'); // e.g., "started", "working", "completed"
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('booking_assignments', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'ended_at', 'current_job_status']);
        });
    }
};
