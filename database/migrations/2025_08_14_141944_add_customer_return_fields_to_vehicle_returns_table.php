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
        Schema::table('vehicle_returns', function (Blueprint $table) {
            $table->json('customer_images')->nullable()->after('images');
            $table->text('customer_condition_notes')->nullable()->after('customer_images');
            $table->enum('status', ['customer_submitted', 'admin_processing', 'completed'])->default('customer_submitted')->after('customer_condition_notes');
            $table->datetime('customer_submitted_at')->nullable()->after('status');
            $table->datetime('admin_processed_at')->nullable()->after('customer_submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_returns', function (Blueprint $table) {
            $table->dropColumn(['customer_images', 'customer_condition_notes', 'status', 'customer_submitted_at', 'admin_processed_at']);
        });
    }
};
