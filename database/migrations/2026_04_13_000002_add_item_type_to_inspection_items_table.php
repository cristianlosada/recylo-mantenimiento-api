<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_items', function (Blueprint $table) {
            // Clasificación del ítem: operativo o lubricación
            // Solo se usa en plantillas de tipo 'production_line'
            $table->enum('item_type', ['operative', 'lubrication'])
                  ->nullable()
                  ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};
