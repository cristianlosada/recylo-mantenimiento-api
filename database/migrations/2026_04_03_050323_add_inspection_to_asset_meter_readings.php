<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Extend reading_source enum to include 'inspection'
        DB::statement("ALTER TABLE asset_meter_readings MODIFY COLUMN reading_source ENUM('manual','work_order','maintenance_plan','import','inspection') NOT NULL DEFAULT 'manual'");

        Schema::table('asset_meter_readings', function (Blueprint $table) {
            $table->unsignedBigInteger('inspection_id')->nullable()->after('maintenance_plan_id');
            $table->foreign('inspection_id')->references('id')->on('inspections')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_meter_readings', function (Blueprint $table) {
            $table->dropForeign(['inspection_id']);
            $table->dropColumn('inspection_id');
        });

        DB::statement("ALTER TABLE asset_meter_readings MODIFY COLUMN reading_source ENUM('manual','work_order','maintenance_plan','import') NOT NULL DEFAULT 'manual'");
    }
};
