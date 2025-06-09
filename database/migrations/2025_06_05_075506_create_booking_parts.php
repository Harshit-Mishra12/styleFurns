<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    //     | Column          | Type          | Description                                        |
    // | --------------- | ------------- | -------------------------------------------------- |
    // | `id`            | BIGINT        | Primary key                                        |
    // | `booking_id`    | FOREIGN KEY   | Related booking                                    |
    // | `part_name`     | STRING        | Name of part/material                              |
    // | `serial_number` | STRING NULL   | If applicable                                      |
    // | `quantity`      | DECIMAL(10,2) | Amount (e.g., 2.5)                                 |
    // | `unit_type`     | ENUM          | `unit`, `gram`, `kg`, `ml`, `liter`, `meter`, etc. |
    // | `price`         | DECIMAL(10,2) | Cost (if technician/admin supplied it)             |
    // | `added_by`      | FOREIGN KEY   | Who added (user\_id)                               |
    // | `added_source`  | ENUM          | `admin`, `technician`, `customer`                  |
    // | `provided_by`   | ENUM          | `admin`, `technician`, `customer`, `unknown`       |
    // | `is_provided`   | BOOLEAN       | Has the part been received?                        |
    // | `is_required`   | BOOLEAN       | Is this part still needed?                         |
    // | `notes`         | TEXT NULL     | Optional comments                                  |
    // | `created_at`    | TIMESTAMP     | —                                                  |
    // | `updated_at`    | TIMESTAMP     | —                                                  |

    public function up()
    {
        Schema::create('booking_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->string('part_name');
            $table->string('serial_number')->nullable();

            $table->decimal('quantity', 10, 2)->default(1);
            $table->enum('unit_type', ['unit', 'gram', 'kg', 'ml', 'liter', 'meter'])->default('unit');

            $table->decimal('price', 10, 2)->nullable(); // price if provided by technician/admin

            $table->foreignId('added_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('added_source', ['admin', 'technician', 'customer'])->default('technician');
            $table->enum('provided_by', ['admin', 'technician', 'customer', 'unknown'])->default('unknown');

            $table->boolean('is_provided')->default(false);
            $table->boolean('is_required')->default(true);
            $table->text('notes')->nullable();

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
        Schema::dropIfExists('booking_parts');
    }
};
