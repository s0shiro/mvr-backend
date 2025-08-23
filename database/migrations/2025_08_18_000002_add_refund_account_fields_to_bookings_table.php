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
            // Add customer refund account information for cancelled bookings
            $table->string('refund_method', 20)->nullable()->after('refund_proof')->comment('Customer preferred refund method: gcash, bank_transfer, cash');
            $table->string('refund_account_number', 100)->nullable()->after('refund_method')->comment('Customer account number for refund');
            $table->string('refund_account_name', 100)->nullable()->after('refund_account_number')->comment('Customer account holder name');
            $table->string('refund_bank_name', 100)->nullable()->after('refund_account_name')->comment('Customer bank name for bank transfer');
            $table->text('refund_customer_notes')->nullable()->after('refund_bank_name')->comment('Customer instructions for refund');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'refund_method',
                'refund_account_number', 
                'refund_account_name',
                'refund_bank_name',
                'refund_customer_notes'
            ]);
        });
    }
};
