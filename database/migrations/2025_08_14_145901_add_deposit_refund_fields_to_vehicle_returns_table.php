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
            $table->enum('deposit_status', ['pending', 'refunded', 'withheld'])->default('pending')->after('cleaning_fee');
            $table->decimal('deposit_refund_amount', 10, 2)->nullable()->after('deposit_status');
            $table->text('deposit_refund_notes')->nullable()->after('deposit_refund_amount');
            $table->json('deposit_refund_proof')->nullable()->after('deposit_refund_notes');
            $table->datetime('deposit_refunded_at')->nullable()->after('deposit_refund_proof');
            $table->string('refund_method')->nullable()->after('deposit_refunded_at'); // e.g., 'cash', 'bank_transfer', 'gcash'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_returns', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_status', 
                'deposit_refund_amount', 
                'deposit_refund_notes', 
                'deposit_refund_proof', 
                'deposit_refunded_at',
                'refund_method'
            ]);
        });
    }
};
