<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el ENUM para agregar 'pending_reception' entre 'consumed' y 'returned'
        DB::statement("ALTER TABLE work_order_materials MODIFY COLUMN material_status ENUM(
            'planned',
            'requested',
            'approved',
            'delivered',
            'in_use',
            'consumed',
            'pending_reception',
            'returned',
            'completed',
            'cancelled'
        ) NOT NULL DEFAULT 'planned' COMMENT 'Estado en el flujo de materiales'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: eliminar 'pending_reception' del ENUM
        DB::statement("ALTER TABLE work_order_materials MODIFY COLUMN material_status ENUM(
            'planned',
            'requested',
            'approved',
            'delivered',
            'in_use',
            'consumed',
            'returned',
            'completed',
            'cancelled'
        ) NOT NULL DEFAULT 'planned' COMMENT 'Estado en el flujo de materiales'");
    }
};
