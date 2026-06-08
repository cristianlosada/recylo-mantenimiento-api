<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recursos planificados por fase (materiales, herramientas, servicios externos)
        Schema::create('project_phase_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained('project_phases')->onDelete('cascade');
            $table->string('resource_type', 30)->comment('material, tool, external_service');
            $table->string('name', 150)->comment('Nombre o descripción del recurso');
            $table->decimal('quantity', 10, 3)->nullable()->comment('Cantidad estimada');
            $table->string('unit', 50)->nullable()->comment('Unidad de medida');
            $table->decimal('unit_cost', 12, 2)->nullable()->comment('Costo unitario estimado');
            $table->decimal('estimated_cost', 12, 2)->nullable()->comment('Costo total estimado');
            $table->decimal('actual_cost', 12, 2)->nullable()->comment('Costo real incurrido');
            $table->foreignId('material_id')->nullable()->constrained('materials')->onDelete('set null')
                  ->comment('Vínculo al ítem de inventario (solo resource_type=material)');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('phase_id');
        });

        // Añadir phase_id a project_warehouse_usages para ligar salidas de almacén a una fase
        Schema::table('project_warehouse_usages', function (Blueprint $table) {
            $table->foreignId('phase_id')->nullable()->after('log_id')
                  ->constrained('project_phases')->onDelete('set null')
                  ->comment('Fase del proyecto a la que se imputa esta salida');
            $table->index('phase_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_warehouse_usages', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
            $table->dropIndex(['phase_id']);
            $table->dropColumn('phase_id');
        });
        Schema::dropIfExists('project_phase_resources');
    }
};
