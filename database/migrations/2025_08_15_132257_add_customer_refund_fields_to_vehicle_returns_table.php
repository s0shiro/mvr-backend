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
            // Customer refund account information
            $table->enum('customer_refund_method', ['bank_transfer', 'gcash', 'cash'])->nullable()->after('customer_condition_notes');
            $table->string('customer_account_name', 100)->nullable()->after('customer_refund_method');
            $table->string('customer_account_number', 50)->nullable()->after('customer_account_name');
            $table->string('customer_bank_name', 100)->nullable()->after('customer_account_number');
            $table->text('customer_refund_notes')->nullable()->after('customer_bank_name')->comment('Additional instructions from customer for refund');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_returns', function (Blueprint $table) {
            $table->dropColumn([
                'customer_refund_method',
                'customer_account_name', 
                'customer_account_number',
                'customer_bank_name',
                'customer_refund_notes'
            ]);
        });
    }
};
