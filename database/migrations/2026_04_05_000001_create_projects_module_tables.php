<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de Proyectos y Montajes
     *
     * Tablas de catálogos (sin enum — patrón code/name/color/is_active):
     *   1. project_statuses
     *   2. project_types
     *   3. project_phase_statuses
     *   4. project_member_roles
     *   5. project_log_statuses
     *   6. project_attachment_types
     *
     * Tablas principales:
     *   7. projects
     *   8. project_phases
     *   9. project_members
     *  10. project_logs
     *  11. project_attachments
     *  12. project_warehouse_usages
     *
     * Modificaciones a tablas existentes:
     *  13. work_orders   → project_id (nullable)
     *  14. work_requests → project_id (nullable)
     */
    public function up(): void
    {
        // ===================================================================
        // 1. CATÁLOGO: project_statuses
        // ===================================================================
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('draft, pending_approval, approved, in_progress, paused, finished, closed, cancelled');
            $table->string('name', 100)->comment('Nombre visible en UI');
            $table->text('description')->nullable();
            $table->string('color', 30)->nullable()->comment('Color para badge UI');
            $table->boolean('is_terminal')->default(false)->comment('Si true, el estado no permite transiciones (closed, cancelled)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // ===================================================================
        // 2. CATÁLOGO: project_types
        // ===================================================================
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('investment, mechanical_assembly, etc.');
            $table->string('name', 100)->comment('Nombre visible en UI');
            $table->string('code_prefix', 10)->comment('Prefijo para código auto-generado: PROY, MMEC, FAB, etc.');
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable()->comment('Icono FontAwesome');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // ===================================================================
        // 3. CATÁLOGO: project_phase_statuses
        // ===================================================================
        Schema::create('project_phase_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('pending, in_progress, completed');
            $table->string('name', 100);
            $table->string('color', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ===================================================================
        // 4. CATÁLOGO: project_member_roles
        // ===================================================================
        Schema::create('project_member_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('leader, supervisor, technician, consultant');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ===================================================================
        // 5. CATÁLOGO: project_log_statuses
        // ===================================================================
        Schema::create('project_log_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('registered, reviewed, validated');
            $table->string('name', 100);
            $table->string('color', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ===================================================================
        // 6. CATÁLOGO: project_attachment_types
        // ===================================================================
        Schema::create('project_attachment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('photo, document, other');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ===================================================================
        // 7. TABLA PRINCIPAL: projects
        // ===================================================================
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // IDENTIFICADORES
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('Empresa');
            $table->string('code', 25)->comment('Código auto-generado: MMEC-2026-001');
            $table->string('name', 150)->comment('Nombre del proyecto');

            // CLASIFICACIÓN (FK a catálogos)
            $table->foreignId('project_type_id')->constrained('project_types')->onDelete('restrict')->comment('Tipo de proyecto');
            $table->foreignId('status_id')->constrained('project_statuses')->onDelete('restrict')->comment('Estado actual');

            // DESCRIPCIÓN
            $table->text('description')->nullable();
            $table->text('objective')->nullable()->comment('Objetivo del proyecto');
            $table->text('justification')->nullable()->comment('Justificación del negocio');

            // RESPONSABLE Y ÁREA
            $table->foreignId('leader_id')->constrained('users')->onDelete('restrict')->comment('Responsable principal');
            $table->foreignId('area_id')->nullable()->constrained('production_lines')->onDelete('set null')->comment('Área / línea de producción');

            // FECHAS
            $table->date('planned_start_date')->comment('Fecha inicio planificada');
            $table->date('planned_end_date')->comment('Fecha fin planificada');
            $table->date('actual_start_date')->nullable()->comment('Fecha inicio real');
            $table->date('actual_end_date')->nullable()->comment('Fecha fin real');

            // ECONOMÍA
            $table->decimal('budget', 15, 2)->nullable()->comment('Presupuesto (requerido si projects.budget_required=true)');
            $table->decimal('actual_cost', 15, 2)->default(0)->comment('Costo real acumulado (calculado automáticamente)');

            // AVANCE
            $table->decimal('progress_percent', 5, 2)->default(0)->comment('% avance: calculado o manual según projects.auto_calculate_progress');

            // CIERRE
            $table->text('closure_notes')->nullable()->comment('Observaciones de cierre (requerido al cerrar)');
            $table->text('lessons_learned')->nullable()->comment('Lecciones aprendidas');

            // TRAZABILIDAD DE TRANSICIONES
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('cancelled_at')->nullable();

            // AUDITORÍA
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // ÍNDICES
            $table->unique(['company_id', 'code'], 'uq_project_code_company');
            $table->index('company_id');
            $table->index('status_id');
            $table->index('project_type_id');
            $table->index('leader_id');
            $table->index('planned_end_date');
        });

        // ===================================================================
        // 8. TABLA: project_phases
        // ===================================================================
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('status_id')->constrained('project_phase_statuses')->onDelete('restrict');
            $table->string('name', 120)->comment('Levantamiento, Montaje, Pruebas…');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0)->comment('Orden dentro del proyecto');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('weight_percent', 5, 2)->default(0)->comment('Peso en el avance total (suma = 100%)');
            $table->decimal('progress_percent', 5, 2)->default(0)->comment('Avance de la fase 0–100, ingresado por el líder');
            $table->foreignId('responsible_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('project_id');
            $table->index('order_index');
        });

        // ===================================================================
        // 9. TABLA: project_members
        // ===================================================================
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('role_id')->constrained('project_member_roles')->onDelete('restrict');
            $table->decimal('hourly_rate', 10, 2)->nullable()->comment('Tarifa hora MO (si projects.labor_cost_enabled=true)');
            $table->date('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true)->comment('Retiro sin eliminar historial');
            $table->timestamps();

            $table->unique(['project_id', 'user_id'], 'uq_project_member');
            $table->index('project_id');
            $table->index('user_id');
        });

        // ===================================================================
        // 10. TABLA: project_logs (Bitácora diaria / PDT)
        // ===================================================================
        Schema::create('project_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('phase_id')->nullable()->constrained('project_phases')->onDelete('set null');
            $table->foreignId('status_id')->constrained('project_log_statuses')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict')->comment('Persona que ejecutó la actividad');
            $table->foreignId('logged_by')->constrained('users')->onDelete('restrict')->comment('Persona que registró (puede diferir en modo cuadrilla)');
            $table->date('log_date')->comment('Fecha de la actividad');
            $table->decimal('hours_worked', 5, 2)->comment('Horas dedicadas (máx 24 por persona/día por proyecto)');
            $table->text('activity_description')->comment('Actividad realizada');
            $table->text('result_description')->comment('Resultado del día (obligatorio)');
            $table->decimal('progress_reported', 5, 2)->nullable()->comment('% avance reportado en este PDT');
            $table->text('findings')->nullable()->comment('Novedades o inconvenientes');
            $table->text('deliverables')->nullable()->comment('Entregables del día');
            $table->decimal('labor_cost', 10, 2)->nullable()->comment('Calculado: hours_worked × hourly_rate');

            // VALIDACIÓN / FLUJO
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();

            $table->index('project_id');
            $table->index('user_id');
            $table->index('log_date');
            $table->index(['user_id', 'log_date', 'project_id'], 'idx_log_hours_validation');
        });

        // ===================================================================
        // 11. TABLA: project_attachments
        // ===================================================================
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('log_id')->nullable()->constrained('project_logs')->onDelete('cascade')->comment('Adjunto de bitácora específica');
            $table->foreignId('phase_id')->nullable()->constrained('project_phases')->onDelete('set null')->comment('Adjunto de fase');
            $table->foreignId('attachment_type_id')->constrained('project_attachment_types')->onDelete('restrict');
            $table->string('file_path')->comment('Ruta almacenada');
            $table->string('original_name')->comment('Nombre original del archivo');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('project_id');
            $table->index('log_id');
        });

        // ===================================================================
        // 12. TABLA: project_warehouse_usages (feature flag: warehouse_integration)
        // ===================================================================
        Schema::create('project_warehouse_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('log_id')->nullable()->constrained('project_logs')->onDelete('set null');
            $table->foreignId('inventory_transaction_id')->constrained('inventory_transactions')->onDelete('restrict')->comment('Salida de almacén vinculada');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('project_id');
        });

        // ===================================================================
        // 13. work_orders → project_id (nullable)
        // ===================================================================
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('maintenance_plan_id')
                ->constrained('projects')->onDelete('set null')
                ->comment('Proyecto al que pertenece esta OT');
            $table->index('project_id', 'idx_wo_project');
        });

        // ===================================================================
        // 14. work_requests → project_id (nullable)
        // ===================================================================
        Schema::table('work_requests', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('company_id')
                ->constrained('projects')->onDelete('set null')
                ->comment('Proyecto al que está asociada esta solicitud');
            $table->index('project_id', 'idx_wr_project');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('work_requests', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });

        Schema::dropIfExists('project_warehouse_usages');
        Schema::dropIfExists('project_attachments');
        Schema::dropIfExists('project_logs');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_phases');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_attachment_types');
        Schema::dropIfExists('project_log_statuses');
        Schema::dropIfExists('project_member_roles');
        Schema::dropIfExists('project_phase_statuses');
        Schema::dropIfExists('project_types');
        Schema::dropIfExists('project_statuses');

        Schema::enableForeignKeyConstraints();
    }
};
