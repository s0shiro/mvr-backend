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
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('updated_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->enum('refund_status', ['pending', 'processed', 'not_applicable'])->default('pending')->after('refund_amount');
            $table->timestamp('refund_processed_at')->nullable()->after('refund_status');
            $table->text('refund_notes')->nullable()->after('refund_processed_at');
            $table->json('refund_proof')->nullable()->after('refund_notes')->comment('Admin proof of refund transaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason', 
                'refund_status',
                'refund_processed_at',
                'refund_notes',
                'refund_proof'
            ]);
        });
    }
};
