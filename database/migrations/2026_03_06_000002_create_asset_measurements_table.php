<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo');
            $table->string('measurement_type', 100)->comment('Tipo de medición: temperature, pressure, vibration, etc.');
            $table->decimal('value', 10, 2)->comment('Valor medido');
            $table->string('unit', 50)->comment('Unidad de medida: °C, PSI, Hz, etc.');
            $table->decimal('min_threshold', 10, 2)->nullable()->comment('Umbral mínimo aceptable');
            $table->decimal('max_threshold', 10, 2)->nullable()->comment('Umbral máximo aceptable');
            $table->enum('status', ['normal', 'warning', 'critical'])->default('normal')->comment('Estado basado en umbrales');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->timestamp('measured_at')->comment('Fecha y hora de la medición');
            $table->foreignId('measured_by')->constrained('users')->onDelete('cascade')->comment('Usuario que registró la medición');
            $table->timestamps();

            // Índices
            $table->index('asset_id');
            $table->index('measurement_type');
            $table->index('status');
            $table->index('measured_at');
            $table->index(['asset_id', 'measurement_type', 'measured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_measurements');
    }
};
