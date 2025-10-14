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
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'fee_per_kilometer')) {
                $table->decimal('fee_per_kilometer', 10, 2)->default(0)->after('deposit');
            }
            if (!Schema::hasColumn('vehicles', 'late_fee_per_hour')) {
                $table->decimal('late_fee_per_hour', 10, 2)->default(0)->after('fee_per_kilometer');
            }
            if (!Schema::hasColumn('vehicles', 'late_fee_per_day')) {
                $table->decimal('late_fee_per_day', 10, 2)->default(0)->after('late_fee_per_hour');
            }
            if (!Schema::hasColumn('vehicles', 'gasoline_late_fee_per_liter')) {
                $table->decimal('gasoline_late_fee_per_liter', 10, 2)->default(0)->after('late_fee_per_day');
            }
            if (!Schema::hasColumn('vehicles', 'fuel_capacity')) {
                $table->integer('fuel_capacity')->nullable()->after('gasoline_late_fee_per_liter');
            }
            if (!Schema::hasColumn('vehicles', 'fuel_type')) {
                $table->string('fuel_type')->nullable()->after('fuel_capacity');
            }
            if (!Schema::hasColumn('vehicles', 'color')) {
                $table->string('color')->nullable()->after('fuel_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'gasoline_late_fee_per_liter')) {
                $table->dropColumn('gasoline_late_fee_per_liter');
            }
            if (Schema::hasColumn('vehicles', 'color')) {
                $table->dropColumn('color');
            }
            if (Schema::hasColumn('vehicles', 'fuel_type')) {
                $table->dropColumn('fuel_type');
            }
            if (Schema::hasColumn('vehicles', 'fuel_capacity')) {
                $table->dropColumn('fuel_capacity');
            }
            if (Schema::hasColumn('vehicles', 'late_fee_per_day')) {
                $table->dropColumn('late_fee_per_day');
            }
            if (Schema::hasColumn('vehicles', 'late_fee_per_hour')) {
                $table->dropColumn('late_fee_per_hour');
            }
            if (Schema::hasColumn('vehicles', 'fee_per_kilometer')) {
                $table->dropColumn('fee_per_kilometer');
            }
        });
    }
};
