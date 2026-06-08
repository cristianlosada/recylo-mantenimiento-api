<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Módulo de Órdenes de Trabajo (Work Orders) - ENTERPRISE GRADE
     * Tablas:
     * - work_orders: Órdenes de trabajo (ejecución de mantenimiento)
     * - work_order_assignments: Técnicos asignados al equipo
     * - work_order_materials: Materiales consumidos
     * - work_order_time_logs: Registro de horas trabajadas
     * - work_order_attachments: Evidencias (fotos antes/después, documentos)
     * - work_order_checklist_items: Checklist de tareas a completar
     * - work_order_comments: Comunicación del equipo
     * - work_order_status_history: Historial de cambios de estado
     */
    public function up(): void
    {
        // 1. TABLA: work_orders
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();

            // IDENTIFICADORES
            $table->string('code', 50)->comment('Código único (WO-202602-0001)');
            $table->string('title', 255)->comment('Título de la orden de trabajo');
            $table->text('description')->comment('Descripción detallada del trabajo a realizar');

            // RELACIONES
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('Empresa');
            $table->foreignId('work_request_id')->nullable()->constrained('work_requests')->onDelete('set null')->comment('Solicitud origen (reactivo)');
            $table->unsignedBigInteger('maintenance_plan_id')->nullable()->comment('Plan preventivo (automático) - FK futura');
            // TODO: Agregar constraint cuando se cree tabla maintenance_plans:
            // $table->foreign('maintenance_plan_id')->references('id')->on('maintenance_plans')->onDelete('set null');
            $table->foreignId('asset_id')->constrained('assets')->onDelete('restrict')->comment('Activo a intervenir');

            // CLASIFICACIÓN
            $table->enum('work_order_type', ['corrective', 'preventive', 'predictive', 'inspection', 'emergency', 'project'])
                ->default('corrective')
                ->comment('Tipo de mantenimiento');
            
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])
                ->default('medium')
                ->comment('Prioridad de ejecución');

            // ESTADO
            $table->enum('status', ['pending', 'scheduled', 'in_progress', 'on_hold', 'completed', 'validated', 'cancelled'])
                ->default('pending')
                ->comment('Estado actual de la orden');

            // PROGRAMACIÓN
            $table->timestamp('scheduled_start')->nullable()->comment('Fecha/hora programada de inicio');
            $table->timestamp('scheduled_end')->nullable()->comment('Fecha/hora programada de finalización');
            $table->decimal('estimated_duration_hours', 8, 2)->nullable()->comment('Duración estimada en horas');

            // EJECUCIÓN REAL
            $table->timestamp('actual_start')->nullable()->comment('Fecha/hora real de inicio');
            $table->timestamp('actual_end')->nullable()->comment('Fecha/hora real de finalización');
            $table->decimal('actual_duration_hours', 8, 2)->nullable()->comment('Duración real en horas');

            // ASIGNACIÓN
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null')->comment('Técnico principal');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null')->comment('Quien asignó');
            $table->timestamp('assigned_at')->nullable()->comment('Fecha de asignación');

            // COSTOS ESTIMADOS
            $table->decimal('estimated_labor_cost', 12, 2)->default(0)->comment('Costo estimado de mano de obra');
            $table->decimal('estimated_material_cost', 12, 2)->default(0)->comment('Costo estimado de materiales');
            $table->decimal('estimated_other_cost', 12, 2)->default(0)->comment('Otros costos estimados');

            // COSTOS REALES (se calculan automáticamente)
            $table->decimal('actual_labor_cost', 12, 2)->default(0)->comment('Costo real de mano de obra');
            $table->decimal('actual_material_cost', 12, 2)->default(0)->comment('Costo real de materiales');
            $table->decimal('actual_other_cost', 12, 2)->default(0)->comment('Otros costos reales');

            // COMPLETADO
            $table->text('completion_notes')->nullable()->comment('Notas al completar el trabajo');
            $table->text('signature_data')->nullable()->comment('Firma digital (JSON con base64)');
            $table->string('signature_name', 255)->nullable()->comment('Nombre del firmante');
            $table->timestamp('signature_date')->nullable()->comment('Fecha de la firma');
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null')->comment('Técnico que completó');
            $table->timestamp('completed_at')->nullable()->comment('Fecha de completado');

            // VALIDACIÓN (Cierre administrativo)
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null')->comment('Supervisor que validó');
            $table->timestamp('validated_at')->nullable()->comment('Fecha de validación');
            $table->text('validation_notes')->nullable()->comment('Notas de validación');

            // CANCELACIÓN
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que canceló');
            $table->timestamp('cancelled_at')->nullable()->comment('Fecha de cancelación');
            $table->text('cancellation_reason')->nullable()->comment('Razón de la cancelación');

            // DATOS ADICIONALES
            $table->string('failure_type', 100)->nullable()->comment('Tipo de falla (si aplica)');
            $table->decimal('downtime_hours', 8, 2)->default(0)->comment('Tiempo de inactividad del activo');
            $table->boolean('is_emergency')->default(false)->comment('¿Es una emergencia?');
            $table->boolean('requires_shutdown')->default(false)->comment('¿Requiere apagar el activo?');

            // SLA
            $table->timestamp('sla_deadline')->nullable()->comment('Fecha límite según SLA');
            $table->boolean('sla_breached')->default(false)->comment('¿Se incumplió el SLA?');
            $table->text('sla_breach_reason')->nullable()->comment('Razón del incumplimiento');

            // AUDITORÍA
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict')->comment('Usuario que creó');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('Última actualización');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que eliminó');

            // ÍNDICES
            $table->unique(['company_id', 'code'], 'unique_work_order_code_per_company');
            $table->index('company_id');
            $table->index('work_request_id');
            $table->index('asset_id');
            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to');
            $table->index('scheduled_start');
            $table->index('created_at');
        });

        // 2. TABLA: work_order_assignments (Múltiples técnicos)
        Schema::create('work_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Técnico asignado');
            $table->enum('role', ['technician', 'supervisor', 'helper', 'specialist'])
                ->default('technician')
                ->comment('Rol en el equipo');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('restrict')->comment('Quien lo asignó');
            $table->timestamp('assigned_at')->useCurrent()->comment('Fecha de asignación');
            $table->text('notes')->nullable()->comment('Notas de asignación');

            // ÍNDICES
            $table->unique(['work_order_id', 'user_id'], 'unique_user_per_order');
            $table->index('work_order_id');
            $table->index('user_id');
        });

        // 3. TABLA: work_order_materials (Materiales consumidos)
        Schema::create('work_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo');
            $table->foreignId('material_id')->constrained('materials')->onDelete('restrict')->comment('Material/repuesto/herramienta');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict')->comment('Almacén origen');

            // ESTADO DEL MATERIAL EN EL FLUJO
            $table->enum('material_status', [
                'planned',      // Planificado al crear la orden
                'requested',    // Solicitado por técnico
                'approved',     // Aprobado por almacenista
                'delivered',    // Entregado físicamente
                'in_use',       // En uso por técnico
                'consumed',     // Consumido (materiales) / Usado (herramientas)
                'returned',     // Devuelto al almacén
                'completed',    // Proceso completado
                'cancelled'     // Cancelado
            ])->default('planned')->comment('Estado en el flujo de materiales');

            // CANTIDADES
            $table->decimal('quantity_planned', 10, 3)->default(0)->comment('Cantidad planificada inicialmente');
            $table->decimal('quantity_requested', 10, 3)->nullable()->comment('Cantidad solicitada por técnico');
            $table->decimal('quantity_approved', 10, 3)->nullable()->comment('Cantidad aprobada por almacén');
            $table->decimal('quantity_delivered', 10, 3)->nullable()->comment('Cantidad entregada físicamente');
            $table->decimal('quantity_consumed', 10, 3)->default(0)->comment('Cantidad realmente consumida/usada');
            $table->decimal('quantity_returned', 10, 3)->nullable()->comment('Cantidad devuelta al almacén');
            
            $table->string('unit', 50)->nullable()->comment('Unidad de medida');

            // COSTOS
            $table->decimal('unit_cost', 12, 2)->nullable()->comment('Costo unitario al momento del consumo');
            $table->decimal('total_cost', 12, 2)->nullable()->comment('Costo total (quantity_consumed * unit_cost)');

            // FECHAS DEL FLUJO
            $table->timestamp('requested_at')->nullable()->comment('Cuándo el técnico solicitó');
            $table->timestamp('approved_at')->nullable()->comment('Cuándo el almacenista aprobó');
            $table->timestamp('delivered_at')->nullable()->comment('Cuándo se entregó físicamente');
            $table->timestamp('consumed_at')->nullable()->comment('Cuándo se consumió/usó');
            $table->timestamp('returned_at')->nullable()->comment('Cuándo se devolvió');
            $table->timestamp('completed_at')->nullable()->comment('Cuándo se completó el proceso');

            // USUARIOS RESPONSABLES
            $table->foreignId('requested_by')->nullable()->constrained('users')->onDelete('set null')->comment('Técnico que solicitó');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->comment('Almacenista que aprobó');
            $table->foreignId('delivered_by')->nullable()->constrained('users')->onDelete('set null')->comment('Almacenista que entregó');
            $table->foreignId('consumed_by')->nullable()->constrained('users')->onDelete('set null')->comment('Técnico que consumió/usó');
            $table->foreignId('returned_by')->nullable()->constrained('users')->onDelete('set null')->comment('Técnico que devolvió');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null')->comment('Almacenista que recibió devolución');

            // NOTAS
            $table->text('request_notes')->nullable()->comment('Notas de la solicitud');
            $table->text('approval_notes')->nullable()->comment('Notas de aprobación/rechazo');
            $table->text('delivery_notes')->nullable()->comment('Notas de entrega');
            $table->text('consumption_notes')->nullable()->comment('Notas de consumo');
            $table->text('return_notes')->nullable()->comment('Notas de devolución');

            // AUDITORÍA
            $table->timestamps();

            // ÍNDICES
            $table->index('work_order_id');
            $table->index('material_id');
            $table->index('warehouse_id');
            $table->index('material_status');
            $table->index('requested_at');
            $table->index('delivered_at');
        });

        // 4. TABLA: work_order_time_logs (Registro de horas trabajadas)
        Schema::create('work_order_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict')->comment('Técnico que trabajó');

            // TIEMPO
            $table->timestamp('start_time')->comment('Hora de inicio');
            $table->timestamp('end_time')->nullable()->comment('Hora de fin');
            $table->decimal('hours_worked', 8, 2)->comment('Horas trabajadas');

            // COSTOS
            $table->decimal('hourly_rate', 10, 2)->comment('Tarifa por hora del técnico');
            $table->decimal('total_cost', 12, 2)->nullable()->comment('Costo total (hours_worked * hourly_rate)');

            // CLASIFICACIÓN
            $table->enum('labor_type', ['regular', 'overtime', 'weekend', 'holiday'])
                ->default('regular')
                ->comment('Tipo de jornada');

            // DESCRIPCIÓN
            $table->text('description')->nullable()->comment('Descripción del trabajo realizado');

            // AUDITORÍA
            $table->timestamps();

            // ÍNDICES
            $table->index('work_order_id');
            $table->index('user_id');
            $table->index('start_time');
        });

        // 5. TABLA: work_order_attachments (Evidencias)
        Schema::create('work_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo');

            // ARCHIVO
            $table->string('file_name', 255)->comment('Nombre original del archivo');
            $table->string('file_path', 500)->comment('Ruta de almacenamiento');
            $table->string('file_type', 100)->comment('Tipo MIME del archivo');
            $table->unsignedInteger('file_size')->comment('Tamaño en bytes');

            // CLASIFICACIÓN
            $table->enum('attachment_type', ['photo_before', 'photo_during', 'photo_after', 'document', 'signature', 'other'])
                ->default('other')
                ->comment('Tipo de evidencia');
            
            $table->text('description')->nullable()->comment('Descripción del adjunto');

            // AUDITORÍA
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict')->comment('Usuario que subió');
            $table->timestamp('uploaded_at')->useCurrent()->comment('Fecha de subida');

            // ÍNDICES
            $table->index('work_order_id');
            $table->index('attachment_type');
        });

        // 6. TABLA: work_order_checklist_items (Checklist)
        Schema::create('work_order_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo');

            // ITEM
            $table->string('item_text', 500)->comment('Texto del item');
            $table->boolean('is_checked')->default(false)->comment('¿Completado?');
            $table->boolean('is_required')->default(false)->comment('¿Es obligatorio?');
            $table->unsignedInteger('display_order')->default(0)->comment('Orden de visualización');

            // REGISTRO
            $table->foreignId('checked_by')->nullable()->constrained('users')->onDelete('set null')->comment('Técnico que marcó');
            $table->timestamp('checked_at')->nullable()->comment('Fecha de marcado');
            $table->text('notes')->nullable()->comment('Notas del item');

            // AUDITORÍA
            $table->timestamps();

            // ÍNDICES
            $table->index('work_order_id');
            $table->index(['work_order_id', 'display_order']);
        });

        // 7. TABLA: work_order_comments (Comunicación)
        Schema::create('work_order_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict')->comment('Usuario que comentó');
            
            // COMENTARIO
            $table->text('comment')->comment('Contenido del comentario');
            $table->boolean('is_internal')->default(false)->comment('¿Visible solo para el equipo?');
            
            // RESPUESTAS
            $table->foreignId('parent_id')->nullable()->constrained('work_order_comments')->onDelete('cascade')->comment('Comentario padre (respuestas)');

            // AUDITORÍA
            $table->timestamps();
            $table->softDeletes();

            // ÍNDICES
            $table->index('work_order_id');
            $table->index('user_id');
            $table->index('parent_id');
        });

        // 8. TABLA: work_order_status_history (Historial)
        Schema::create('work_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo');
            
            // CAMBIO
            $table->string('from_status', 50)->nullable()->comment('Estado anterior');
            $table->string('to_status', 50)->comment('Nuevo estado');
            
            // REGISTRO
            $table->foreignId('changed_by')->constrained('users')->onDelete('restrict')->comment('Usuario que cambió');
            $table->timestamp('changed_at')->useCurrent()->comment('Fecha del cambio');
            
            // CONTEXTO
            $table->text('reason')->nullable()->comment('Razón del cambio');
            $table->json('metadata')->nullable()->comment('Datos adicionales del cambio');

            // ÍNDICES
            $table->index('work_order_id');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar en orden inverso por dependencias
        Schema::dropIfExists('work_order_status_history');
        Schema::dropIfExists('work_order_comments');
        Schema::dropIfExists('work_order_checklist_items');
        Schema::dropIfExists('work_order_attachments');
        Schema::dropIfExists('work_order_time_logs');
        Schema::dropIfExists('work_order_materials');
        Schema::dropIfExists('work_order_assignments');
        Schema::dropIfExists('work_orders');
    }
};
