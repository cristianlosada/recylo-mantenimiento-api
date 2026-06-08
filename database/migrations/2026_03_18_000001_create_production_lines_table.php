<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla: production_lines
     *
     * Almacena las líneas de producción / áreas de proceso parametrizables por empresa.
     * Requerido por HU-A1 — campo "Área o proceso o línea de producción" en Activos.
     *
     * Estándar: softDeletes para entidades administradas por el usuario.
     */
    public function up(): void
    {
        Schema::create('production_lines', function (Blueprint $table) {
            $table->id()->comment('PK. Línea de producción');
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade')
                ->comment('FK a companies.id — empresa propietaria');
            $table->string('code', 50)->comment('Código único por empresa (SULF_I, FOSF, GRAN, etc.)');
            $table->string('name', 150)->comment('Nombre de la línea o proceso (Sulfúrico I, Fosfórico, etc.)');
            $table->text('description')->nullable()->comment('Descripción adicional del área');
            $table->boolean('is_active')->default(true)->comment('Estado activo de la línea');
            $table->timestamps();
            $table->softDeletes()->comment('Borrado lógico');

            $table->unique(['company_id', 'code'], 'uq_production_line_code_company');
            $table->index('company_id', 'idx_production_lines_company');
            $table->index(['company_id', 'is_active'], 'idx_production_lines_company_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_lines');
    }
};
