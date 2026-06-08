<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Este archivo crea TODAS las tablas del módulo de Planes de Mantenimiento:
     * 1. asset_meters (medidores/contadores de activos)
     * 2. asset_meter_readings (historial de lecturas)
     * 3. maintenance_plans (planes de mantenimiento)
     * 4. maintenance_plan_checklist_templates (checklist predefinido)
     * 5. maintenance_plan_material_templates (materiales predefinidos)
     * 6. maintenance_plan_executions (historial de ejecuciones)
     */
    public function up(): void
    {
        // ===================================================================
        // 1. ASSET METERS (Medidores/Contadores de Activos)
        // ===================================================================
        Schema::create('asset_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo al que pertenece el medidor');
            
            // Tipo de medidor
            $table->enum('meter_type', ['hours', 'kilometers', 'cycles', 'units_produced'])
                ->comment('Tipo de medidor: horas de uso, kilómetros, ciclos de operación, unidades producidas');
            
            // Lectura actual
            $table->decimal('current_reading', 12, 2)->default(0)->comment('Lectura actual del medidor');
            $table->string('unit', 20)->comment('Unidad de medida: h, km, ciclos, unidades');
            
            // Control de actualizaciones
            $table->timestamp('last_reading_date')->nullable()->comment('Fecha y hora de la última lectura registrada');
            $table->foreignId('last_reading_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que registró la última lectura');
            
            // Estado
            $table->boolean('is_active')->default(true)->comment('Si el medidor está activo o deshabilitado');
            
            // Notas
            $table->text('notes')->nullable()->comment('Notas sobre el medidor');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('asset_id');
            $table->index('meter_type');
            $table->index('is_active');
            $table->index('last_reading_date');
            
            // Un activo solo puede tener UN medidor de cada tipo
            $table->unique(['asset_id', 'meter_type'], 'unique_asset_meter_type');
        });

        // ===================================================================
        // 2. ASSET METER READINGS (Historial de Lecturas)
        // ===================================================================
        Schema::create('asset_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_meter_id')->constrained('asset_meters')->onDelete('cascade')->comment('Medidor al que pertenece la lectura');
            
            // Valores de la lectura
            $table->decimal('reading_value', 12, 2)->comment('Valor de la lectura');
            $table->decimal('previous_value', 12, 2)->nullable()->comment('Lectura anterior (para calcular diferencia)');
            $table->decimal('difference', 12, 2)->nullable()->comment('Incremento desde la última lectura');
            
            // Fecha y origen
            $table->timestamp('reading_date')->comment('Fecha y hora de la lectura');
            $table->enum('reading_source', ['manual', 'work_order', 'maintenance_plan', 'import'])
                ->default('manual')
                ->comment('Origen de la lectura: manual, desde OT, desde plan, importación');
            
            // Referencias opcionales
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->onDelete('set null')->comment('OT relacionada si fue registrada durante una OT');
            $table->unsignedBigInteger('maintenance_plan_id')->nullable()->comment('Plan de mantenimiento relacionado (FK se agrega después)');
            
            // Usuario que registró
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade')->comment('Usuario que registró la lectura');
            
            // Notas
            $table->text('notes')->nullable()->comment('Notas adicionales sobre la lectura');
            
            $table->timestamps();
            
            // Índices
            $table->index('asset_meter_id');
            $table->index('reading_date');
            $table->index('reading_source');
            $table->index(['asset_meter_id', 'reading_date']);
        });

        // ===================================================================
        // 3. MAINTENANCE PLANS (Planes de Mantenimiento)
        // ===================================================================
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('Empresa');
            $table->string('code', 50)->unique()->comment('Código único: MP-202603-00001');
            
            // Activo y ubicación
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo al que aplica el plan');
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->onDelete('set null')->comment('Categoría del activo');
            $table->foreignId('site_id')->nullable()->constrained('company_sites')->onDelete('set null')->comment('Sitio del activo');
            
            // Información básica
            $table->string('name')->comment('Nombre del plan de mantenimiento');
            $table->text('description')->nullable()->comment('Descripción detallada del plan');
            
            // Tipo de plan
            $table->enum('plan_type', ['time_based', 'meter_based', 'hybrid'])
                ->comment('Tipo: basado en tiempo, medición o híbrido');
            
            // Configuración TIEMPO (para time_based y hybrid)
            $table->enum('frequency_type', ['daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual'])
                ->nullable()
                ->comment('Tipo de frecuencia temporal');
            $table->integer('frequency_value')->nullable()->comment('Valor de frecuencia: cada X días/semanas/meses');
            
            // Configuración MEDICIÓN (para meter_based y hybrid)
            $table->enum('meter_type', ['hours', 'kilometers', 'cycles', 'units_produced'])
                ->nullable()
                ->comment('Tipo de medidor a evaluar');
            $table->decimal('meter_threshold', 12, 2)->nullable()->comment('Umbral de medición: cada X horas/km/ciclos');
            
            // Configuración HÍBRIDA
            $table->enum('trigger_mode', ['first', 'both'])
                ->nullable()
                ->comment('Modo híbrido: first=el que ocurra primero, both=ambos deben cumplirse');
            
            // Prioridad y estimaciones
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->comment('Prioridad del plan');
            $table->integer('estimated_duration_minutes')->nullable()->comment('Duración estimada en minutos');
            $table->decimal('estimated_cost', 12, 2)->nullable()->comment('Costo estimado');
            
            // Asignación por defecto
            $table->foreignId('default_assigned_to')->nullable()->constrained('users')->onDelete('set null')->comment('Técnico asignado por defecto');
            
            // Estado y control de ejecución
            $table->boolean('is_active')->default(true)->comment('Si el plan está activo o pausado');
            $table->timestamp('last_execution_date')->nullable()->comment('Fecha de última ejecución');
            $table->decimal('last_meter_reading', 12, 2)->nullable()->comment('Lectura del medidor en última ejecución');
            
            // Próxima ejecución (calculado automáticamente)
            $table->timestamp('next_execution_date')->nullable()->comment('Próxima fecha de ejecución (planes time_based)');
            $table->decimal('next_meter_threshold', 12, 2)->nullable()->comment('Próximo umbral de medición (planes meter_based)');
            
            // Auditoría
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que creó el plan');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que modificó por última vez');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('company_id');
            $table->index('asset_id');
            $table->index('plan_type');
            $table->index('is_active');
            $table->index('next_execution_date');
            $table->index(['company_id', 'is_active']);
            $table->index(['asset_id', 'is_active']);
        });

        // ===================================================================
        // 4. MAINTENANCE PLAN CHECKLIST TEMPLATES (Checklist Predefinido)
        // ===================================================================
        Schema::create('maintenance_plan_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained('maintenance_plans')->onDelete('cascade')->comment('Plan de mantenimiento');
            
            // Orden y contenido
            $table->integer('item_order')->comment('Orden de ejecución del ítem');
            $table->string('item_text')->comment('Texto del ítem del checklist');
            
            // Configuración
            $table->boolean('requires_photo')->default(false)->comment('¿Requiere foto?');
            $table->boolean('is_mandatory')->default(true)->comment('¿Es obligatorio completarlo?');
            
            $table->timestamps();
            
            // Índices
            $table->index('maintenance_plan_id');
            $table->index(['maintenance_plan_id', 'item_order'], 'mp_checklist_plan_order_idx');
        });

        // ===================================================================
        // 5. MAINTENANCE PLAN MATERIAL TEMPLATES (Materiales Predefinidos)
        // ===================================================================
        Schema::create('maintenance_plan_material_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained('maintenance_plans')->onDelete('cascade')->comment('Plan de mantenimiento');
            $table->foreignId('material_id')->constrained('materials')->onDelete('cascade')->comment('Material o herramienta');
            
            // Cantidad estimada
            $table->decimal('estimated_quantity', 10, 2)->default(1)->comment('Cantidad estimada necesaria');
            
            // Notas
            $table->text('notes')->nullable()->comment('Notas sobre el uso del material');
            
            $table->timestamps();
            
            // Índices
            $table->index('maintenance_plan_id');
            $table->index('material_id');
        });

        // ===================================================================
        // 6. MAINTENANCE PLAN EXECUTIONS (Historial de Ejecuciones)
        // ===================================================================
        Schema::create('maintenance_plan_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained('maintenance_plans')->onDelete('cascade')->comment('Plan de mantenimiento');
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo generada');
            
            // Fechas
            $table->timestamp('scheduled_date')->comment('Fecha programada de ejecución');
            $table->timestamp('executed_date')->nullable()->comment('Fecha real de ejecución (cuando se completó la OT)');
            
            // Medición al momento de ejecución
            $table->decimal('meter_reading_at_execution', 12, 2)->nullable()->comment('Lectura del medidor al momento de ejecutar');
            
            // Estado
            $table->enum('status', ['scheduled', 'completed', 'skipped', 'overdue'])
                ->default('scheduled')
                ->comment('Estado: programado, completado, omitido, atrasado');
            
            // Notas
            $table->text('notes')->nullable()->comment('Notas sobre la ejecución');
            
            $table->timestamps();
            
            // Índices
            $table->index('maintenance_plan_id');
            $table->index('work_order_id');
            $table->index('status');
            $table->index('scheduled_date');
            $table->index(['maintenance_plan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_executions');
        Schema::dropIfExists('maintenance_plan_material_templates');
        Schema::dropIfExists('maintenance_plan_checklist_templates');
        Schema::dropIfExists('maintenance_plans');
        Schema::dropIfExists('asset_meter_readings');
        Schema::dropIfExists('asset_meters');
    }
};
