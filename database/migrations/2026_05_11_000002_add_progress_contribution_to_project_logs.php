<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_logs', function (Blueprint $table) {
            // Stores the actual contribution applied to the phase after capping at 100%
            $table->decimal('progress_contribution', 5, 2)->unsigned()->nullable()->after('progress_reported');
        });

        // Backfill: set contribution = reported for existing rows (no capping on historical data)
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE project_logs SET progress_contribution = progress_reported WHERE progress_reported IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::table('project_logs', function (Blueprint $table) {
            $table->dropColumn('progress_contribution');
        });
    }
};
