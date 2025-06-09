<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('booking_assignments', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('reason');
            $table->text('admin_reason')->nullable()->after('comment');
            $table->timestamp('admin_updated_at')->nullable()->after('admin_reason');
        });
    }

    public function down()
    {
        Schema::table('booking_assignments', function (Blueprint $table) {
            $table->dropColumn('admin_updated_at');
            $table->dropColumn('admin_reason');
            $table->dropColumn('comment');
        });
    }
};
