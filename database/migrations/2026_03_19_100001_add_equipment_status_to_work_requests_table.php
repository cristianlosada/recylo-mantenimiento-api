<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            // Estado del equipo al momento de la solicitud (HU-S1)
            $table->enum('equipment_status', ['operating_restricted', 'full_stop'])
                ->nullable()
                ->after('priority')
                ->comment('Estado del equipo: operando con restricción o paro total');
        });
    }

    public function down(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->dropColumn('equipment_status');
        });
    }
};
