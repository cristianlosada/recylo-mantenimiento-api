<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas: maintenance_types + asset_maintenance_types
     *
     * maintenance_types: catálogo de especialidades técnicas parametrizable por empresa.
     * asset_maintenance_types: pivot de multiselección activo <-> tipo de mantenimiento.
     *
     * Requerido por HU-A4 — "Tipo de mantenimiento del activo".
     */
    public function up(): void
    {
        // Catálogo de tipos de mantenimiento (parametrizable por empresa)
        Schema::create('maintenance_types', function (Blueprint $table) {
            $table->id()->comment('PK. Tipo de mantenimiento');
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade')
                ->comment('FK a companies.id — empresa propietaria');
            $table->string('code', 50)->comment('Código único por empresa (MECH, ELEC, INSTR, etc.)');
            $table->string('name', 100)->comment('Nombre del tipo (Mecánico, Eléctrico, etc.)');
            $table->text('description')->nullable()->comment('Descripción del tipo de mantenimiento');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();
            $table->softDeletes()->comment('Borrado lógico');

            $table->unique(['company_id', 'code'], 'uq_maintenance_type_code_company');
            $table->index('company_id', 'idx_maintenance_types_company');
            $table->index(['company_id', 'is_active'], 'idx_maintenance_types_company_active');
        });

        // Tabla pivot activo <-> tipo de mantenimiento (multiselección ordenada)
        Schema::create('asset_maintenance_types', function (Blueprint $table) {
            $table->id()->comment('PK. Relación activo-tipo de mantenimiento');
            $table->foreignId('asset_id')
                ->constrained('assets')
                ->onDelete('cascade')
                ->comment('FK a assets.id');
            $table->foreignId('maintenance_type_id')
                ->constrained('maintenance_types')
                ->onDelete('cascade')
                ->comment('FK a maintenance_types.id');
            $table->unsignedTinyInteger('order_index')
                ->default(0)
                ->comment('Orden de selección del usuario (define prioridad de especialidad)');
            $table->timestamps();

            $table->unique(['asset_id', 'maintenance_type_id'], 'uq_asset_maintenance_type');
            $table->index('asset_id', 'idx_asset_maintenance_types_asset');
            $table->index('maintenance_type_id', 'idx_asset_maintenance_types_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_types');
        Schema::dropIfExists('maintenance_types');
    }
};
