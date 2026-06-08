<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de control de acceso (RBAC): módulos, permisos, roles y asignaciones
     * Consolidación completa: reemplaza todas las migraciones de modules, permissions, roles
     * y sus variantes de enhance/add
     * 
     * Estructura:
     * - modules: catálogo de módulos funcionales del sistema
     * - permissions: permisos granulares (module_id + action)
     * - roles: roles de acceso (sistema o por empresa)
     * - role_permissions: asignación n:m entre roles y permisos
     * - role_delegations: delegación de roles de un usuario a otro
     */
    public function up(): void
    {
        // Tabla de módulos del sistema
        Schema::create('modules', function (Blueprint $table) {
            $table->id()->comment('PK. Módulo funcional del sistema');
            $table->string('code', 80)->unique()->comment('Código único del módulo (MAINTENANCE, INVENTORY, QUALITY, etc.)');
            $table->string('name', 190)->comment('Nombre del módulo visible en UI');
            $table->string('description')->nullable()->comment('Descripción del módulo');
            $table->string('icon', 50)->nullable()->comment('Icono del módulo para UI');
            $table->integer('order')->default(0)->comment('Orden de visualización del módulo');
            $table->boolean('is_core')->default(false)->comment('1 si es parte del core del sistema');
            $table->boolean('is_active')->default(true)->comment('Indica si el módulo está activo');
            $table->timestamps();
            
            $table->index('is_active', 'idx_modules_active');
            $table->index('is_core', 'idx_modules_core');
        });

        // Tabla de permisos granulares (normalizados con FK a modules)
        Schema::create('permissions', function (Blueprint $table) {
            $table->id()->comment('PK. Permiso granular del sistema');
            $table->string('code', 100)->unique()->comment('Código único del permiso (ej: MAINTENANCE.VIEW, MAINTENANCE.CREATE)');
            $table->string('name', 120)->comment('Nombre legible del permiso');
            $table->foreignId('module_id')->nullable()->constrained('modules')->onDelete('cascade')->comment('FK. Módulo al que pertenece el permiso');
            $table->string('action', 120)->comment('Acción: view, create, update, delete, approve, export, etc.');
            $table->string('description', 255)->nullable()->comment('Descripción del permiso');
            $table->boolean('is_active')->default(true)->comment('Estado activo del permiso');
            $table->timestamps();
            
            $table->unique(['module_id', 'action'], 'uq_perm_module_action');
            $table->index('module_id', 'idx_perm_module');
            $table->index('is_active', 'idx_perm_active');
        });

        // Tabla de roles (puede ser sistema o por empresa)
        Schema::create('roles', function (Blueprint $table) {
            $table->id()->comment('PK. Rol de acceso');
            $table->string('name', 120)->comment('Nombre legible del rol');
            $table->string('code', 120)->unique()->comment('Código único del rol (UPPER_SNAKE_CASE)');
            $table->text('description')->nullable()->comment('Descripción del rol');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade')->comment('FK. Empresa a la que pertenece el rol (null = rol del sistema)');
            $table->boolean('is_system')->default(false)->comment('Indica si es un rol del sistema (no editable)');
            $table->boolean('is_active')->default(true)->comment('Indica si el rol está activo');
            $table->timestamps();
            
            $table->index('company_id', 'idx_roles_company');
            $table->index('is_system', 'idx_roles_system');
            $table->index('is_active', 'idx_roles_active');
            $table->index(['company_id', 'is_active'], 'idx_roles_company_active');
        });

        // Tabla de asignación de permisos a roles (n:m)
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade')->comment('FK a roles.id');
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade')->comment('FK a permissions.id');
            $table->timestamps();
            
            $table->primary(['role_id', 'permission_id']);
            $table->index('role_id', 'idx_roleperm_role');
            $table->index('permission_id', 'idx_roleperm_perm');
        });

        // Tabla de delegación de roles (un usuario delega su rol a otro)
        Schema::create('role_delegations', function (Blueprint $table) {
            $table->id()->comment('PK. Delegación de rol');
            $table->foreignId('delegator_user_id')->constrained('users')->onDelete('cascade')->comment('FK a users.id - usuario que delega');
            $table->foreignId('delegatee_user_id')->constrained('users')->onDelete('cascade')->comment('FK a users.id - usuario que recibe');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade')->comment('FK a roles.id - rol delegado');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade')->comment('FK a companies.id - empresa donde aplica (opcional)');
            $table->text('reason')->nullable()->comment('Motivo de la delegación');
            $table->timestamp('delegated_at')->comment('Fecha de delegación');
            $table->timestamp('expires_at')->nullable()->comment('Fecha de expiración de la delegación');
            $table->timestamp('revoked_at')->nullable()->comment('Fecha de revocación (si aplica)');
            $table->timestamps();
            
            $table->index('delegator_user_id', 'idx_delegator');
            $table->index('delegatee_user_id', 'idx_delegatee');
            $table->index('role_id', 'idx_delegation_role');
            $table->index('company_id', 'idx_delegation_company');
            $table->index('expires_at', 'idx_delegation_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('role_delegations');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('modules');
        Schema::enableForeignKeyConstraints();
    }
};
