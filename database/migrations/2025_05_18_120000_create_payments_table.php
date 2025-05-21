<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->enum('method', ['gcash', 'bank_transfer']);
            $table->string('reference_number');
            $table->longText('proof_image'); // base64
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('type', ['deposit', 'rental'])->default('deposit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
