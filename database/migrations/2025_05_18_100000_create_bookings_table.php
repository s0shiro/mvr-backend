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
            $table->timestampTz('start_date');
            $table->timestampTz('end_date');
            $table->enum('status', ['pending', 'confirmed', 'for_release', 'released', 'cancelled', 'completed'])->default('pending');
            $table->decimal('total_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('driver_requested')->default(false); 
            $table->enum('pickup_type', ['pickup', 'delivery'])->default('pickup');
            $table->string('delivery_location')->nullable();
            $table->text('delivery_details')->nullable()->comment('Barangay, landmark, and additional delivery instructions');
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->json('valid_ids')->nullable()->comment('Base64-encoded images of two valid IDs');
            $table->unsignedInteger('days')->nullable()->comment('Number of days for the booking');
            $table->decimal('refund_rate', 4, 2)->nullable()->comment('Refund rate applied on cancellation');
            $table->decimal('refund_amount', 10, 2)->nullable()->comment('Refund amount applied on cancellation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
