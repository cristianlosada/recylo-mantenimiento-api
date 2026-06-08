<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega nuevo estado 'converted_to_work_order' a work_requests
     * para indicar cuando una solicitud se convirtió en orden de trabajo
     */
    public function up(): void
    {
        // Modificar el enum de status para incluir el nuevo estado
        DB::statement("ALTER TABLE work_requests MODIFY COLUMN status ENUM(
            'pending',
            'approved',
            'rejected',
            'in_progress',
            'completed',
            'cancelled',
            'converted_to_work_order'
        ) NOT NULL DEFAULT 'pending' COMMENT 'Estado de la solicitud'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al enum original (sin converted_to_work_order)
        DB::statement("ALTER TABLE work_requests MODIFY COLUMN status ENUM(
            'pending',
            'approved',
            'rejected',
            'in_progress',
            'completed',
            'cancelled'
        ) NOT NULL DEFAULT 'pending' COMMENT 'Estado de la solicitud'");
    }
};
