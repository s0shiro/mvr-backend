<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_returns', 'fuel_fee')) {
                $table->decimal('fuel_fee', 10, 2)->default(0)->after('cleaning_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_returns', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_returns', 'fuel_fee')) {
                $table->dropColumn('fuel_fee');
            }
        });
    }
};
