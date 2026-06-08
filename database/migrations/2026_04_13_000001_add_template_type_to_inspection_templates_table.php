<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_templates', function (Blueprint $table) {
            // Tipo de plantilla: maquinaria amarilla (existente) o línea de producción (nuevo)
            $table->enum('template_type', ['yellow_machinery', 'production_line'])
                  ->default('yellow_machinery')
                  ->after('name');

            // FK a línea de producción — solo aplica cuando template_type = 'production_line'
            $table->foreignId('production_line_id')
                  ->nullable()
                  ->after('template_type')
                  ->constrained('production_lines')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_templates', function (Blueprint $table) {
            $table->dropForeign(['production_line_id']);
            $table->dropColumn(['template_type', 'production_line_id']);
        });
    }
};
