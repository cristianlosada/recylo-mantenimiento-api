<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de auditoría, configuraciones y catálogos de apoyo
     * Consolidación: audit_actions, audit_logs, system_settings, company_settings, currencies
     */
    public function up(): void
    {
        // Tabla de monedas (catálogo)
        Schema::create('currencies', function (Blueprint $table) {
            $table->id()->comment('PK. Moneda');
            $table->string('code', 3)->unique()->comment('Código ISO de la moneda (COP, USD, EUR)');
            $table->string('name', 100)->comment('Nombre de la moneda');
            $table->string('symbol', 10)->comment('Símbolo de la moneda ($, €, etc.)');
            $table->integer('decimal_places')->default(2)->comment('Número de decimales');
            $table->boolean('is_active')->default(true)->comment('Indica si la moneda está activa');
            $table->timestamps();
            
            $table->index('is_active', 'idx_currencies_active');
        });

        // Tabla de acciones auditables
        Schema::create('audit_actions', function (Blueprint $table) {
            $table->id()->comment('PK. Acción auditable');
            $table->string('name', 100)->unique()->comment('Nombre de la acción (CREATE_USER, UPDATE_COMPANY, etc.)');
            $table->string('description')->comment('Descripción de la acción');
            $table->string('module', 50)->comment('Módulo al que pertenece (USER, COMPANY, MAINTENANCE, etc.)');
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('MEDIUM')->comment('Nivel de severidad para auditoría');
            $table->boolean('log_details')->default(true)->comment('Indica si registrar detalles de cambios');
            $table->boolean('is_active')->default(true)->comment('Indica si está activa');
            $table->timestamps();
            
            $table->index(['module', 'severity'], 'idx_audit_actions_module_severity');
            $table->index('is_active', 'idx_audit_actions_active');
        });

        // Tabla de registros de auditoría
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id()->comment('PK. Registro de auditoría');
            $table->foreignId('audit_action_id')->constrained('audit_actions')->onDelete('restrict')->comment('FK a audit_actions.id');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('FK a users.id - usuario que ejecutó');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('set null')->comment('FK a companies.id - contexto de empresa');
            $table->string('entity_type', 100)->comment('Tipo de entidad afectada (User, Company, Order, etc.)');
            $table->string('entity_id', 100)->comment('ID de la entidad afectada');
            $table->json('old_values')->nullable()->comment('Valores anteriores (para updates)');
            $table->json('new_values')->nullable()->comment('Valores nuevos');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP del usuario');
            $table->string('user_agent')->nullable()->comment('User agent del navegador');
            $table->text('additional_info')->nullable()->comment('Información adicional');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at'], 'idx_audit_user_date');
            $table->index(['company_id', 'created_at'], 'idx_audit_company_date');
            $table->index(['entity_type', 'entity_id'], 'idx_audit_entity');
            $table->index(['audit_action_id', 'created_at'], 'idx_audit_action_date');
        });

        // Tabla de configuraciones del sistema
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id()->comment('PK. Configuración del sistema');
            $table->string('key', 100)->unique()->comment('Clave de configuración (tax_rate, default_currency, etc.)');
            $table->text('value')->comment('Valor de la configuración');
            $table->string('description')->nullable()->comment('Descripción de la configuración');
            $table->enum('type', ['string', 'number', 'boolean', 'json'])->default('string')->comment('Tipo de dato');
            $table->boolean('is_public')->default(false)->comment('Indica si es visible públicamente');
            $table->timestamps();
            
            $table->index('key', 'idx_system_settings_key');
        });

        // Tabla de configuraciones específicas de empresa
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id()->comment('PK. Configuración específica de empresa');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('FK a companies.id');
            $table->string('key', 100)->comment('Clave de configuración');
            $table->text('value')->comment('Valor de la configuración');
            $table->string('description')->nullable()->comment('Descripción de la configuración');
            $table->enum('type', ['string', 'number', 'boolean', 'json'])->default('string')->comment('Tipo de dato');
            $table->timestamps();
            
            $table->unique(['company_id', 'key'], 'uq_company_setting');
            $table->index('company_id', 'idx_company_settings_company');
            $table->index('key', 'idx_company_settings_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('audit_actions');
        Schema::dropIfExists('currencies');
        Schema::enableForeignKeyConstraints();
    }
};
