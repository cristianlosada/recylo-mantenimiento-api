<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de relaciones multi-tenant: usuarios en empresas, roles asignados y módulos habilitados
     * Consolidación completa: user_companies, user_roles, company_enabled_modules
     * y todas sus dependencias
     */
    public function up(): void
    {
        // Tabla de planes de suscripción (catálogo)
        Schema::create('plans', function (Blueprint $table) {
            $table->id()->comment('PK. Plan de suscripción');
            $table->string('code', 50)->unique()->comment('Código del plan (STARTER, PROFESSIONAL, ENTERPRISE, etc.)');
            $table->string('name', 120)->comment('Nombre del plan');
            $table->text('description')->nullable()->comment('Descripción del plan');
            $table->decimal('price', 15, 2)->default(0)->comment('Precio del plan');
            $table->string('currency', 3)->default('USD')->comment('Moneda (ISO 4217)');
            $table->integer('billing_cycle_days')->default(30)->comment('Días del ciclo de facturación');
            $table->boolean('is_active')->default(true)->comment('Plan disponible para nuevos clientes');
            $table->timestamps();
            
            $table->index('is_active', 'idx_plans_active');
        });

        // Tabla de módulos habilitados para planes
        Schema::create('plan_modules', function (Blueprint $table) {
            $table->id()->comment('PK. Módulo habilitado en plan');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade')->comment('FK a plans.id');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade')->comment('FK a modules.id');
            $table->boolean('included')->default(true)->comment('Indica si el módulo está incluido en el plan');
            $table->integer('max_users')->nullable()->comment('Límite de usuarios (null = sin límite)');
            $table->integer('max_records')->nullable()->comment('Límite de registros (null = sin límite)');
            $table->timestamps();
            
            $table->unique(['plan_id', 'module_id'], 'uq_plan_module');
            $table->index('plan_id', 'idx_planmod_plan');
            $table->index('module_id', 'idx_planmod_module');
        });

        // Tabla de estados de suscripción (catálogo)
        Schema::create('subscription_statuses', function (Blueprint $table) {
            $table->id()->comment('PK. Estado de suscripción');
            $table->string('code', 50)->unique()->comment('Código del estado (ACTIVE, INACTIVE, SUSPENDED, EXPIRED, etc.)');
            $table->string('name', 120)->comment('Nombre del estado');
            $table->text('description')->nullable()->comment('Descripción');
            $table->boolean('is_active')->default(true)->comment('Estado disponible para asignación');
            $table->timestamps();
        });

        // Tabla de suscripciones de empresas a planes
        Schema::create('company_plan_subscriptions', function (Blueprint $table) {
            $table->id()->comment('PK. Suscripción empresa-plan');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('FK a companies.id');
            $table->foreignId('plan_id')->constrained('plans')->comment('FK a plans.id');
            $table->foreignId('subscription_status_id')->constrained('subscription_statuses')->comment('FK a subscription_statuses.id');
            $table->date('start_date')->comment('Fecha de inicio de la suscripción');
            $table->date('end_date')->nullable()->comment('Fecha de fin de la suscripción');
            $table->decimal('amount', 15, 2)->nullable()->comment('Monto pagado/contratado');
            $table->timestamps();
            
            $table->index('company_id', 'idx_subscription_company');
            $table->index('plan_id', 'idx_subscription_plan');
            $table->index('subscription_status_id', 'idx_subscription_status');
        });

        // Tabla de membresía usuario-empresa con detalles laborales
        Schema::create('user_companies', function (Blueprint $table) {
            $table->id()->comment('PK. Membresía usuario-empresa');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('FK a users.id');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('FK a companies.id');
            $table->string('employee_code', 50)->nullable()->comment('Código único del empleado en la empresa');
            $table->foreignId('site_id')->nullable()->constrained('company_sites')->onDelete('set null')->comment('FK a company_sites.id - sitio por defecto');
            
            // Información organizacional (opcional, se puede normalizar a tablas separadas si crece)
            $table->string('department', 120)->nullable()->comment('Departamento');
            $table->string('job_position', 120)->nullable()->comment('Cargo/Posición');
            $table->enum('employment_type', ['full_time', 'part_time', 'contractor', 'intern'])->nullable()->comment('Tipo de contratación');
            $table->foreignId('direct_supervisor_id')->nullable()->constrained('users')->onDelete('set null')->comment('FK a users.id - supervisor directo');
            
            // Fechas laborales
            $table->date('hire_date')->nullable()->comment('Fecha de contratación');
            $table->date('termination_date')->nullable()->comment('Fecha de terminación');
            $table->string('termination_reason', 255)->nullable()->comment('Motivo de terminación');
            
            // Compensación (sensible - considerar cifrado)
            $table->decimal('salary_amount', 15, 2)->nullable()->comment('Salario base');
            $table->string('salary_currency', 3)->nullable()->comment('Moneda del salario (ISO 4217)');
            
            // Estatus en la empresa
            $table->boolean('is_primary')->default(false)->comment('Marca la empresa principal del usuario');
            $table->enum('status', ['active', 'inactive', 'suspended', 'terminated'])->default('active')->comment('Estado del empleado');
            $table->timestamps();
            
            $table->unique(['user_id', 'company_id'], 'uq_user_company');
            $table->unique(['company_id', 'employee_code'], 'uq_employee_code_company');
            $table->index('site_id', 'idx_uc_site');
            $table->index('direct_supervisor_id', 'idx_uc_supervisor');
            $table->index(['company_id', 'status'], 'idx_uc_company_status');
            $table->index(['company_id', 'site_id', 'status'], 'idx_uc_company_site_status');
        });

        // Tabla de asignación de roles a usuarios (por empresa)
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id()->comment('PK. Rol asignado a usuario');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('FK a users.id');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade')->comment('FK a roles.id');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade')->comment('FK a companies.id - contexto del rol (null = sistema)');
            $table->timestamp('assigned_at')->nullable()->comment('Fecha de asignación');
            $table->timestamp('expires_at')->nullable()->comment('Fecha de expiración del rol');
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id', 'company_id'], 'uq_user_role_company');
            $table->index('user_id', 'idx_ur_user');
            $table->index('role_id', 'idx_ur_role');
            $table->index('company_id', 'idx_ur_company');
            $table->index(['user_id', 'company_id'], 'idx_ur_user_company');
        });

        // Tabla de módulos habilitados para empresas
        Schema::create('company_enabled_modules', function (Blueprint $table) {
            $table->id()->comment('PK. Módulo habilitado para empresa');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->comment('FK a companies.id');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade')->comment('FK a modules.id');
            $table->boolean('enabled')->default(true)->comment('Indica si el módulo está activo para la empresa');
            $table->json('config')->nullable()->comment('Configuración específica del módulo para la empresa');
            $table->timestamps();
            
            $table->unique(['company_id', 'module_id'], 'uq_company_module');
            $table->index('company_id', 'idx_cem_company');
            $table->index('module_id', 'idx_cem_module');
            $table->index('enabled', 'idx_cem_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('company_enabled_modules');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('user_companies');
        Schema::dropIfExists('company_plan_subscriptions');
        Schema::dropIfExists('subscription_statuses');
        Schema::dropIfExists('plan_modules');
        Schema::dropIfExists('plans');
        Schema::enableForeignKeyConstraints();
    }
};
