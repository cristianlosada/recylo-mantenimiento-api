<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Módulo de Solicitudes de Trabajo (Work Requests) - ENTERPRISE GRADE
     * Tablas:
     * - work_requests: Solicitudes de mantenimiento/reparación
     * - work_request_attachments: Adjuntos (fotos, documentos)
     * - work_request_comments: Conversaciones y comentarios
     * - work_request_watchers: Usuarios que siguen la solicitud
     * - work_request_checklist_templates: Plantillas de checklist por categoría/activo
     * - work_request_checklist_items: Items de checklist para cada solicitud
     * - work_request_tags: Sistema de etiquetas/categorías flexibles
     * - work_request_tag_assignments: Asignación de tags a solicitudes
     * - work_request_related: Vinculación entre solicitudes relacionadas
     * - work_request_status_history: Historial completo de cambios de estado
     * - work_request_notifications: Log de notificaciones enviadas
     */
    public function up(): void
    {
        // 1. TABLA: work_requests
        Schema::create('work_requests', function (Blueprint $table) {
            $table->id();

            // IDENTIFICADORES
            $table->string('code', 50)->comment('Código único (WR-2026-0001)');
            $table->string('title', 255)->comment('Título descriptivo de la solicitud');
            $table->text('description')->nullable()->comment('Descripción detallada del problema o necesidad');

            // RELACIONES
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('Empresa');
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo relacionado');
            
            // SOLICITANTE
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade')->comment('Usuario que solicita');
            $table->string('requester_contact', 255)->nullable()->comment('Contacto alternativo del solicitante');

            // CLASIFICACIÓN
            $table->enum('request_type', ['corrective', 'preventive', 'improvement', 'inspection'])
                ->default('corrective')
                ->comment('Tipo: correctivo, preventivo, mejora, inspección');
            
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])
                ->default('medium')
                ->comment('Prioridad de atención');

            // UBICACIÓN (opcional si difiere del activo)
            $table->text('location_details')->nullable()->comment('Detalles adicionales de ubicación');

            // ESTADO
            $table->enum('status', ['pending', 'approved', 'rejected', 'in_progress', 'completed', 'cancelled'])
                ->default('pending')
                ->comment('Estado de la solicitud');

            // ESTIMACIONES
            $table->decimal('estimated_cost', 12, 2)->nullable()->comment('Costo estimado de la intervención');
            $table->decimal('estimated_hours', 8, 2)->nullable()->comment('Horas estimadas de trabajo');

            // COSTOS REALES (se llenan al finalizar)
            $table->decimal('actual_cost', 12, 2)->nullable()->comment('Costo real de la intervención');
            $table->decimal('actual_hours', 8, 2)->nullable()->comment('Horas reales de trabajo');

            // SLA (Service Level Agreement)
            $table->timestamp('response_due_at')->nullable()->comment('Fecha límite de respuesta según SLA');
            $table->timestamp('resolution_due_at')->nullable()->comment('Fecha límite de resolución según SLA');
            $table->timestamp('first_response_at')->nullable()->comment('Primera respuesta del equipo');
            $table->boolean('sla_breached')->default(false)->comment('¿Se incumplió el SLA?');
            $table->text('sla_breach_reason')->nullable()->comment('Razón del incumplimiento SLA');

            // APROBACIÓN
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que aprobó');
            $table->timestamp('approved_at')->nullable()->comment('Fecha de aprobación');

            // RECHAZO
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que rechazó');
            $table->timestamp('rejected_at')->nullable()->comment('Fecha de rechazo');
            $table->text('rejection_reason')->nullable()->comment('Razón del rechazo');

            // RELACIÓN CON WORK ORDER (se agregará constraint cuando exista work_orders)
            $table->unsignedBigInteger('work_order_id')->nullable()->comment('Orden de trabajo generada (FK futura)');
            // TODO: Agregar constraint cuando se cree tabla work_orders:
            // $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('set null');

            // QR CODE
            $table->string('qr_code_url', 500)->nullable()->comment('URL del código QR generado para esta solicitud');
            $table->timestamp('qr_code_generated_at')->nullable()->comment('Fecha y hora de generación del QR code');

            // AUDITORÍA
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que creó');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario última actualización');
            $table->timestamps();
            $table->softDeletes();

            // ÍNDICES
            $table->unique(['company_id', 'code'], 'unique_work_request_code_per_company');
            $table->index('company_id');
            $table->index('asset_id');
            $table->index('requester_id');
            $table->index(['company_id', 'status'], 'idx_company_status');
            $table->index('status');
            $table->index('request_type');
            $table->index('priority');
            $table->index('approved_by');
            $table->index('rejected_by');
            $table->index('work_order_id');
            $table->index('created_at');
            $table->index('deleted_at');
        });

        // 2. TABLA: work_request_attachments
        Schema::create('work_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud');
            $table->string('file_name', 255)->comment('Nombre original del archivo');
            $table->string('file_path', 500)->comment('Ruta del archivo almacenado');
            $table->string('file_type', 50)->nullable()->comment('Tipo MIME (image/jpeg, application/pdf)');
            $table->unsignedInteger('file_size')->nullable()->comment('Tamaño en bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que subió');
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de carga');

            // ÍNDICES
            $table->index('work_request_id');
            $table->index('uploaded_by');
            $table->index('created_at');
        });

        // 3. TABLA: work_request_comments (Conversaciones)
        Schema::create('work_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Usuario que comenta');
            $table->text('comment')->comment('Contenido del comentario');
            $table->boolean('is_internal')->default(false)->comment('Comentario interno (solo equipo mantenimiento)');
            $table->foreignId('parent_id')->nullable()->constrained('work_request_comments')->onDelete('cascade')->comment('Respuesta a otro comentario');
            $table->timestamps();
            $table->softDeletes();

            // ÍNDICES
            $table->index('work_request_id');
            $table->index('user_id');
            $table->index('parent_id');
            $table->index('is_internal');
            $table->index('created_at');
        });

        // 4. TABLA: work_request_watchers (Seguidores)
        Schema::create('work_request_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Usuario que sigue');
            $table->foreignId('added_by')->nullable()->constrained('users')->onDelete('set null')->comment('Quién lo agregó');
            $table->timestamp('watched_at')->useCurrent()->comment('Fecha de seguimiento');

            // ÍNDICES
            $table->unique(['work_request_id', 'user_id'], 'unique_work_request_watcher');
            $table->index('work_request_id');
            $table->index('user_id');
        });

        // 5. TABLA: work_request_checklist_templates (Plantillas configurables)
        Schema::create('work_request_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('Empresa');
            $table->string('name', 255)->comment('Nombre de la plantilla');
            $table->text('description')->nullable()->comment('Descripción del propósito');
            $table->json('checklist_items')->comment('Items del checklist (array de objetos con: text, is_required, order)');
            
            // APLICABILIDAD (una plantilla puede aplicar a múltiples criterios)
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->onDelete('cascade')->comment('Categoría de activo');
            $table->enum('request_type', ['corrective', 'preventive', 'improvement', 'inspection'])->nullable()->comment('Tipo de solicitud');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->nullable()->comment('Prioridad');
            
            $table->boolean('is_active')->default(true)->comment('Plantilla activa');
            $table->boolean('is_mandatory')->default(false)->comment('Checklist obligatorio para aprobar');
            $table->integer('display_order')->default(0)->comment('Orden de visualización');
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Creador');
            $table->timestamps();
            $table->softDeletes();

            // ÍNDICES
            $table->index('company_id');
            $table->index('asset_category_id');
            $table->index(['asset_category_id', 'request_type'], 'idx_category_request_type');
            $table->index('is_active');
            $table->index('display_order');
        });

        // 6. TABLA: work_request_checklist_items (Items del checklist por solicitud)
        Schema::create('work_request_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud');
            $table->foreignId('template_id')->nullable()->constrained('work_request_checklist_templates')->onDelete('set null')->comment('Plantilla origen');
            
            $table->string('item_text', 500)->comment('Texto del item a verificar');
            $table->boolean('is_checked')->default(false)->comment('¿Está verificado?');
            $table->boolean('is_required')->default(false)->comment('¿Es obligatorio?');
            $table->integer('display_order')->default(0)->comment('Orden de visualización');
            
            $table->foreignId('checked_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que verificó');
            $table->timestamp('checked_at')->nullable()->comment('Fecha de verificación');
            $table->text('notes')->nullable()->comment('Notas adicionales sobre esta verificación');
            
            $table->timestamps();

            // ÍNDICES
            $table->index('work_request_id');
            $table->index('template_id');
            $table->index('is_checked');
            $table->index('is_required');
            $table->index('display_order');
        });

        // 7. TABLA: work_request_tags (Sistema de etiquetas)
        Schema::create('work_request_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('Empresa');
            $table->string('name', 100)->comment('Nombre del tag');
            $table->string('slug', 100)->comment('Slug para URLs');
            $table->string('color', 20)->nullable()->comment('Color hexadecimal (#FF5733)');
            $table->text('description')->nullable()->comment('Descripción del uso');
            $table->boolean('is_active')->default(true)->comment('Tag activo');
            $table->timestamps();

            // ÍNDICES
            $table->unique(['company_id', 'slug'], 'unique_tag_slug_per_company');
            $table->index('company_id');
            $table->index('is_active');
        });

        // 8. TABLA: work_request_tag_assignments (Relación solicitudes-tags)
        Schema::create('work_request_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud');
            $table->foreignId('tag_id')->constrained('work_request_tags')->onDelete('cascade')->comment('Tag');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que asignó');
            $table->timestamp('assigned_at')->useCurrent()->comment('Fecha de asignación');
            $table->timestamps();

            // ÍNDICES
            $table->unique(['work_request_id', 'tag_id'], 'unique_work_request_tag');
            $table->index('work_request_id');
            $table->index('tag_id');
        });

        // 9. TABLA: work_request_related (Solicitudes relacionadas)
        Schema::create('work_request_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud principal');
            $table->foreignId('related_work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud relacionada');
            $table->enum('relationship_type', ['duplicate', 'related', 'blocks', 'caused_by', 'parent', 'child'])
                ->comment('Tipo de relación');
            $table->text('notes')->nullable()->comment('Notas sobre la relación');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que vinculó');
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de vinculación');

            // ÍNDICES
            $table->index('work_request_id');
            $table->index('related_work_request_id');
            $table->index('relationship_type');
        });

        // 10. TABLA: work_request_status_history (Historial de cambios de estado)
        Schema::create('work_request_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud');
            $table->string('from_status', 50)->nullable()->comment('Estado anterior (null cuando se crea)');
            $table->string('to_status', 50)->comment('Estado nuevo');
            $table->text('reason')->nullable()->comment('Razón del cambio');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que cambió');
            $table->timestamp('changed_at')->useCurrent()->comment('Fecha del cambio');
            $table->json('metadata')->nullable()->comment('Datos adicionales (JSON)');

            // ÍNDICES
            $table->index('work_request_id');
            $table->index('from_status');
            $table->index('to_status');
            $table->index('changed_by');
            $table->index('changed_at');
        });

        // 11. TABLA: work_request_notifications (Log de notificaciones)
        Schema::create('work_request_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained('work_requests')->onDelete('cascade')->comment('Solicitud');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Usuario destinatario');
            $table->enum('notification_type', ['created', 'updated', 'approved', 'rejected', 'commented', 'mentioned', 'status_changed'])
                ->comment('Tipo de notificación');
            $table->string('channel', 50)->comment('Canal (email, push, sms, in_app)');
            $table->enum('status', ['pending', 'sent', 'failed', 'read'])->default('pending')->comment('Estado del envío');
            $table->string('title', 255)->comment('Título de la notificación');
            $table->text('message')->comment('Mensaje de la notificación');
            $table->timestamp('sent_at')->nullable()->comment('Fecha de envío');
            $table->timestamp('read_at')->nullable()->comment('Fecha de lectura');
            $table->text('error_message')->nullable()->comment('Mensaje de error si falló');
            $table->timestamps();

            // ÍNDICES
            $table->index('work_request_id');
            $table->index('user_id');
            $table->index('notification_type');
            $table->index('status');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_request_notifications');
        Schema::dropIfExists('work_request_status_history');
        Schema::dropIfExists('work_request_related');
        Schema::dropIfExists('work_request_tag_assignments');
        Schema::dropIfExists('work_request_tags');
        Schema::dropIfExists('work_request_checklist_items');
        Schema::dropIfExists('work_request_checklist_templates');
        Schema::dropIfExists('work_request_watchers');
        Schema::dropIfExists('work_request_comments');
        Schema::dropIfExists('work_request_attachments');
        Schema::dropIfExists('work_requests');
    }
};
