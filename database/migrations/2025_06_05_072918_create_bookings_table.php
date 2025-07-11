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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('damage_desc');
            $table->date('scheduled_date')->nullable();
            $table->string('status')->default('waiting_approval'); // 'pending', 'completed','waiting_approval', 'rescheduling_required'('waiting_parts', 'cancelled','car accident' ,'missing_parts','road bloakage','heavy snow' ,'sickenss',"arrived_late" etc)
            $table->foreignId('current_technician_id')->nullable()->constrained('users');
            $table->integer('slots_required')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->foreignId('customer_id')->constrained('customers');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_active')->default(true);

            // ✅ New fields
            $table->text('remark')->nullable();
            $table->text('status_comment')->nullable();
            $table->json('required_skills')->nullable(); // Store array of skill IDs

            $table->timestamps();
            $table->index('scheduled_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};
