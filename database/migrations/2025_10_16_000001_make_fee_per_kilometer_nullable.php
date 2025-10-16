<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE vehicles ALTER COLUMN fee_per_kilometer DROP DEFAULT');
        DB::statement('ALTER TABLE vehicles ALTER COLUMN fee_per_kilometer DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE vehicles ALTER COLUMN fee_per_kilometer SET DEFAULT 0');
        DB::statement('UPDATE vehicles SET fee_per_kilometer = 0 WHERE fee_per_kilometer IS NULL');
        DB::statement('ALTER TABLE vehicles ALTER COLUMN fee_per_kilometer SET NOT NULL');
    }
};
