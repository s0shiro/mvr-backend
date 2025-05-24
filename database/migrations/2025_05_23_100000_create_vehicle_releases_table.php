<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->text('condition_notes')->nullable();
            $table->string('fuel_level')->nullable();
            $table->integer('odometer')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('images')->nullable(); // for optional photos
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_releases');
    }
};
