<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('maintenance_date');
            $table->string('maintenance_type', 100);
            $table->decimal('amount', 10, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'maintenance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenances');
    }
};
