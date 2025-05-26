<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // car, van, etc.
            $table->string('brand');
            $table->string('model');
            $table->integer('year');
            $table->string('plate_number')->unique();
            $table->integer('capacity'); // number of passengers
            $table->decimal('rental_rate', 10, 2); // per day without driver
            $table->decimal('rental_rate_with_driver', 10, 2); // per day with driver
            $table->decimal('deposit', 10, 2)->default(0); // deposit required for vehicle
            $table->text('description')->nullable();
            $table->string('status')->default('available'); // available, maintenance, rented
            $table->timestamps();
            $table->softDeletes(); // for keeping rental history
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
