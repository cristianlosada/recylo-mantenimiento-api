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
        Schema::create('asset_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo');
            $table->string('activity_type', 50)->comment('Tipo de actividad: work_order_created, work_order_completed, work_request_created, measurement_added, etc.');
            $table->string('title')->comment('Título de la actividad');
            $table->text('description')->nullable()->comment('Descripción detallada');
            
            // Referencias opcionales a entidades relacionadas
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->onDelete('set null')->comment('OT relacionada');
            $table->foreignId('work_request_id')->nullable()->constrained('work_requests')->onDelete('set null')->comment('Solicitud relacionada');
            
            // Plan de mantenimiento (sin FK constraint por ahora, la tabla no existe aún)
            $table->unsignedBigInteger('maintenance_plan_id')->nullable()->comment('Plan de mantenimiento relacionado');
            
            // Metadatos adicionales en JSON
            $table->json('metadata')->nullable()->comment('Datos adicionales como prioridad, estado anterior/nuevo, etc.');
            
            // Usuario que realizó la acción
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que realizó la acción');
            
            $table->timestamp('performed_at')->comment('Cuándo ocurrió la actividad');
            $table->timestamps();

            // ÍNDICES para búsquedas eficientes
            $table->index('asset_id');
            $table->index('activity_type');
            $table->index('performed_at');
            $table->index(['asset_id', 'activity_type']);
            $table->index(['asset_id', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_activity_log');
    }
};
