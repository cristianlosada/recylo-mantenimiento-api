<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_phase_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained('project_phases')->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('project_phase_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('project_phase_statuses')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
        });

        // Add on_hold status and fix existing colors to hex
        DB::table('project_phase_statuses')->updateOrInsert(
            ['code' => 'on_hold'],
            ['name' => 'En pausa', 'color' => '#f59e0b', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('project_phase_statuses')->where('code', 'pending')->update(['color' => '#6b7280']);
        DB::table('project_phase_statuses')->where('code', 'in_progress')->update(['color' => '#3b82f6']);
        DB::table('project_phase_statuses')->where('code', 'completed')->update(['color' => '#22c55e']);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phase_status_histories');
        DB::table('project_phase_statuses')->where('code', 'on_hold')->delete();
    }
};
