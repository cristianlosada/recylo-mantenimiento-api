<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_phase_status_histories', function (Blueprint $table) {
            $table->string('type', 30)->default('status_changed')->after('phase_id');
            $table->json('changes')->nullable()->after('notes');
        });

        // Make to_status_id nullable to allow created/edited events without status
        DB::statement('ALTER TABLE project_phase_status_histories MODIFY to_status_id BIGINT UNSIGNED NULL');

        // Backfill existing rows
        DB::table('project_phase_status_histories')->update(['type' => 'status_changed']);
    }

    public function down(): void
    {
        Schema::table('project_phase_status_histories', function (Blueprint $table) {
            $table->dropColumn(['type', 'changes']);
        });

        DB::statement('ALTER TABLE project_phase_status_histories MODIFY to_status_id BIGINT UNSIGNED NOT NULL');
    }
};
