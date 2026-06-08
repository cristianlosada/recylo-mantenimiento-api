<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Módulo de Activos (CMMS - Computerized Maintenance Management System)
     * Tablas:
     * - asset_categories: Categorías de activos
     * - asset_statuses: Estados del ciclo de vida
     * - asset_priorities: Niveles de criticidad
     * - assets: Tabla principal con jerarquía
     * - asset_specifications: Especificaciones técnicas (relacional)
     * - asset_users: Relación activos-usuarios (responsables)
     * - asset_notes: Notas del activo
     * - asset_notifications: Configuración de notificaciones
     * - asset_spare_parts: Repuestos asociados al activo
     * - asset_attachments: Archivos adjuntos del activo
     */
    public function up(): void
    {
        // 1. TABLA: asset_categories
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Código único (EQUIPMENT, INSTALLATION, TOOLS)');
            $table->string('name', 100)->comment('Nombre de la categoría');
            $table->text('description')->nullable()->comment('Descripción detallada');
            $table->string('icon', 50)->nullable()->comment('Icono CSS o emoji');
            $table->string('color', 20)->nullable()->comment('Color hexadecimal para UI');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();

            // Índices
            $table->index('is_active');
        });

        // 2. TABLA: asset_statuses
        Schema::create('asset_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Código único (ACTIVE, MAINTENANCE, OUT_OF_SERVICE)');
            $table->string('name', 100)->comment('Nombre del estado');
            $table->text('description')->nullable()->comment('Descripción');
            $table->string('color', 20)->nullable()->comment('Color para badges (success, warning, danger)');
            $table->boolean('requires_note')->default(false)->comment('Si requiere nota al cambiar a este estado');
            $table->boolean('is_operational')->default(true)->comment('Si el activo está operativo en este estado');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();

            // Índices
            $table->index('is_active');
            $table->index('is_operational');
        });

        // 3. TABLA: asset_priorities
        Schema::create('asset_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Código único (LOW, MEDIUM, HIGH, CRITICAL)');
            $table->string('name', 100)->comment('Nombre de la prioridad');
            $table->integer('level')->comment('Nivel numérico (1-4) para ordenamiento');
            $table->string('color', 20)->nullable()->comment('Color para UI');
            $table->text('description')->nullable()->comment('Descripción');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();

            // Índices
            $table->index('level');
            $table->index('is_active');
        });

        // 4. TABLA: assets (Tabla principal con jerarquía)
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // IDENTIFICADORES
            $table->string('code', 50)->comment('Código del activo (TB-001, CE-4Y57B437)');
            $table->string('name', 200)->comment('Nombre del activo');
            $table->text('description')->nullable()->comment('Descripción detallada');

            // RELACIONES
            $table->foreignId('company_id')->constrained('companies')->onDelete('restrict')->comment('Empresa propietaria');
            $table->foreignId('company_site_id')->nullable()->constrained('company_sites')->onDelete('set null')->comment('Sede donde está ubicado');
            $table->foreignId('parent_id')->nullable()->constrained('assets')->onDelete('set null')->comment('Activo padre (jerarquía)');
            $table->foreignId('category_id')->constrained('asset_categories')->onDelete('restrict')->comment('Categoría del activo');
            $table->foreignId('status_id')->constrained('asset_statuses')->onDelete('restrict')->comment('Estado actual');
            $table->foreignId('priority_id')->nullable()->constrained('asset_priorities')->onDelete('set null')->comment('Nivel de criticidad');

            // DATOS TÉCNICOS
            $table->string('brand', 100)->nullable()->comment('Marca (ABB, Samsung, HP, Lenovo)');
            $table->string('model', 100)->nullable()->comment('Modelo (2020, MON-300, LAP-4500)');
            $table->string('serial_number', 100)->nullable()->comment('Número de serie (ABB-S468)');
            $table->decimal('capacity', 12, 2)->nullable()->comment('Capacidad (250.00)');
            $table->string('capacity_unit', 50)->nullable()->comment('Unidad (kVA, HP, L, m³)');
            $table->integer('manufacturing_year')->nullable()->comment('Año de fabricación (2020, 2023)');
            $table->json('materials_used')->nullable()->comment('Materiales/componentes (JSON flexible)');

            // UBICACIÓN FÍSICA
            $table->string('location_path', 500)->nullable()->comment('Ruta completa jerárquica (/Edificio/Recepción/PC)');
            $table->string('location_details', 255)->nullable()->comment('Detalles adicionales de ubicación');
            $table->decimal('latitude', 10, 8)->nullable()->comment('Latitud GPS');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Longitud GPS');

            // COSTOS
            $table->decimal('purchase_cost', 15, 2)->nullable()->comment('Costo de adquisición');
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->onDelete('set null')->comment('Moneda del costo');
            $table->date('purchase_date')->nullable()->comment('Fecha de compra');
            $table->string('cost_center', 100)->nullable()->comment('Centro de costo contable');

            // QR Y MULTIMEDIA
            $table->string('qr_code', 255)->nullable()->comment('Código QR generado (hash o ruta)');
            $table->string('image_path', 255)->nullable()->comment('Ruta de imagen principal');

            // CONTROL
            $table->boolean('is_active')->default(true)->comment('Estado activo/inactivo');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que creó');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario última actualización');
            $table->timestamps();
            $table->softDeletes();

            // ÍNDICES
            $table->unique(['company_id', 'code'], 'unique_asset_code_per_company');
            $table->index('company_id');
            $table->index('company_site_id');
            $table->index('parent_id');
            $table->index('category_id');
            $table->index('status_id');
            $table->index('priority_id');
            $table->index('qr_code');
            $table->index('location_path');
            $table->index('is_active');
            $table->index('deleted_at');
        });

        // 5. TABLA: asset_specifications (Especificaciones técnicas relacionales)
        Schema::create('asset_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo al que pertenece');
            $table->string('spec_key', 100)->comment('Clave de especificación (voltage, frequency, power)');
            $table->string('spec_value', 255)->comment('Valor de la especificación (220V, 60Hz, 5kW)');
            $table->string('spec_unit', 50)->nullable()->comment('Unidad de medida (V, Hz, kW, kg, cm)');
            $table->enum('spec_type', ['text', 'number', 'date', 'boolean'])->default('text')->comment('Tipo de dato');
            $table->integer('display_order')->default(0)->comment('Orden de visualización en UI');
            $table->timestamps();

            // ÍNDICES
            $table->index(['asset_id', 'spec_key'], 'idx_asset_spec_key');
            $table->index('spec_key');
            $table->index('spec_value');
            $table->index('display_order');
        });

        // 6. TABLA: asset_users (Relación muchos a muchos activos-usuarios)
        Schema::create('asset_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Usuario');
            $table->enum('role', ['responsible', 'operator', 'supervisor', 'maintainer'])->comment('Rol del usuario');
            $table->timestamp('assigned_at')->useCurrent()->comment('Fecha de asignación');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null')->comment('Quién asignó');
            $table->timestamps();

            // ÍNDICES
            $table->unique(['asset_id', 'user_id', 'role'], 'unique_asset_user_role');
            $table->index('asset_id');
            $table->index('user_id');
            $table->index('role');
        });

        // 7. TABLA: asset_notes (Notas del activo)
        Schema::create('asset_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo');
            $table->text('text')->comment('Contenido de la nota');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade')->comment('Usuario que creó la nota');
            $table->timestamps();

            // ÍNDICES
            $table->index('asset_id');
            $table->index('created_by');
            $table->index('created_at');
        });

        // 8. TABLA: asset_notifications (Configuración de notificaciones)
        Schema::create('asset_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo');
            $table->string('email')->comment('Email para notificaciones');
            $table->boolean('notify_on_create')->default(true)->comment('Notificar al crear OT');
            $table->boolean('notify_on_open')->default(false)->comment('Notificar al abrir OT');
            $table->boolean('notify_on_close')->default(true)->comment('Notificar al cerrar OT');
            $table->timestamps();

            // ÍNDICES
            $table->index('asset_id');
            $table->index('email');
        });

        // 9. TABLA: asset_spare_parts (Repuestos asociados)
        Schema::create('asset_spare_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo');
            $table->foreignId('material_id')->constrained('materials')->onDelete('cascade')->comment('Material/Repuesto');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade')->comment('Usuario que asoció el repuesto');
            $table->timestamps();

            // ÍNDICES
            $table->unique(['asset_id', 'material_id'], 'unique_asset_material');
            $table->index('asset_id');
            $table->index('material_id');
        });

        // 10. TABLA: asset_attachments (Archivos adjuntos)
        Schema::create('asset_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo');
            $table->string('file_name')->comment('Nombre del archivo');
            $table->string('file_path')->comment('Ruta del archivo en storage');
            $table->string('file_type')->nullable()->comment('Tipo MIME del archivo');
            $table->unsignedBigInteger('file_size')->nullable()->comment('Tamaño en bytes');
            $table->string('description')->nullable()->comment('Descripción del archivo');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade')->comment('Usuario que subió el archivo');
            $table->timestamps();

            // ÍNDICES
            $table->index('asset_id');
            $table->index('uploaded_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_attachments');
        Schema::dropIfExists('asset_spare_parts');
        Schema::dropIfExists('asset_notifications');
        Schema::dropIfExists('asset_notes');
        Schema::dropIfExists('asset_users');
        Schema::dropIfExists('asset_specifications');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_priorities');
        Schema::dropIfExists('asset_statuses');
        Schema::dropIfExists('asset_categories');
    }
};
