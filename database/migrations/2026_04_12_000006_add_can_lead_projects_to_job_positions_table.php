<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->boolean('can_lead_projects')->default(false)->after('is_active')
                  ->comment('Indica si este cargo está autorizado para liderar proyectos');
        });

        // Marcar los cargos directivos existentes (IDs 32-38)
        DB::table('job_positions')
            ->whereIn('id', [32, 33, 34, 35, 36, 37, 38])
            ->update(['can_lead_projects' => true]);
    }

    public function down(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->dropColumn('can_lead_projects');
        });
    }
};
