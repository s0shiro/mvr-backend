<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('status', ['pending', 'confirmed', 'for_release', 'cancelled', 'completed'])->default('pending');
            $table->decimal('total_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('driver_requested')->default(false);
            $table->enum('pickup_type', ['pickup', 'delivery'])->default('pickup');
            $table->string('delivery_location')->nullable();
            $table->text('delivery_details')->nullable()->comment('Barangay, landmark, and additional delivery instructions');
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
