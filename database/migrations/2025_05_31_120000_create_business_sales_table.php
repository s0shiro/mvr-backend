<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->date('date')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('type')->default('sale'); // sale, note, refund, etc.
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_sales');
    }
};
