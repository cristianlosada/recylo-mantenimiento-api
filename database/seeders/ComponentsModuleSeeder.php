<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComponentsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔩 Iniciando seeder de módulo de Componentes...');

        $this->seedPermissions();
        $this->assignPermissionsToRoles();
        $this->seedCompanySetting();

        $this->command->info('✅ Módulo de Componentes configurado exitosamente');
    }

    private function seedPermissions(): void
    {
        // Los permisos de componentes se agregan al módulo INVENTORY existente
        $module = DB::table('modules')->where('code', 'INVENTORY')->first();

        if (!$module) {
            $this->command->error('❌ Módulo INVENTORY no encontrado. Ejecuta InventoryModuleSeeder primero.');
            return;
        }

        $permissions = [
            [
                'module' => 'INVENTORY',
                'action' => 'CREATE_COMPONENT',
                'description' => 'Crear componentes',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'READ_COMPONENT',
                'description' => 'Consultar componentes y stock',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'UPDATE_COMPONENT',
                'description' => 'Actualizar componentes',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'DELETE_COMPONENT',
                'description' => 'Eliminar componentes del catálogo',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'MANAGE_COMPONENT_TYPES',
                'description' => 'Gestionar tipos de componentes (Rodamiento, Motor, Banda…)',
            ],
        ];

        foreach ($permissions as $permission) {
            $code = $permission['module'] . '_' . $permission['action'];

            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'code'        => $code,
                    'name'        => $permission['description'],
                    'action'      => $permission['action'],
                    'description' => $permission['description'],
                    'module_id'   => $module->id,
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }

        $this->command->info('✅ 5 permisos de Componentes creados en módulo INVENTORY');
    }

    private function assignPermissionsToRoles(): void
    {
        $roleSuperAdmin      = DB::table('roles')->where('code', 'SUPER_ADMIN')->first();
        $roleAdmin           = DB::table('roles')->where('code', 'ADMIN')->first();
        $roleSupervisor      = DB::table('roles')->where('code', 'SUPERVISOR')->first();
        $roleWarehouseManager = DB::table('roles')->where('code', 'WAREHOUSE_MANAGER')->first();
        $roleEmployee        = DB::table('roles')->where('code', 'EMPLOYEE')->first();
        $roleViewer          = DB::table('roles')->where('code', 'VIEWER')->first();

        if (!$roleSuperAdmin || !$roleAdmin) {
            $this->command->error('❌ Roles SUPER_ADMIN / ADMIN no encontrados');
            return;
        }

        $getPermissionId = fn($module, $action) => DB::table('permissions')
            ->where('code', $module . '_' . $action)
            ->value('id');

        // === SUPER ADMIN & ADMIN — acceso total ===
        $fullPermissions = [
            $getPermissionId('INVENTORY', 'CREATE_COMPONENT'),
            $getPermissionId('INVENTORY', 'READ_COMPONENT'),
            $getPermissionId('INVENTORY', 'UPDATE_COMPONENT'),
            $getPermissionId('INVENTORY', 'DELETE_COMPONENT'),
            $getPermissionId('INVENTORY', 'MANAGE_COMPONENT_TYPES'),
        ];

        foreach ([$roleSuperAdmin, $roleAdmin] as $role) {
            foreach (array_filter($fullPermissions) as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $role->id, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // === SUPERVISOR — gestión completa excepto eliminar y gestionar tipos ===
        $supervisorPermissions = [
            $getPermissionId('INVENTORY', 'CREATE_COMPONENT'),
            $getPermissionId('INVENTORY', 'READ_COMPONENT'),
            $getPermissionId('INVENTORY', 'UPDATE_COMPONENT'),
            $getPermissionId('INVENTORY', 'MANAGE_COMPONENT_TYPES'),
        ];

        if ($roleSupervisor) {
            foreach (array_filter($supervisorPermissions) as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleSupervisor->id, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // === WAREHOUSE MANAGER (Almacenista) — igual que supervisor ===
        if ($roleWarehouseManager) {
            foreach (array_filter($supervisorPermissions) as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleWarehouseManager->id, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // === EMPLOYEE (Técnico) — solo lectura ===
        $employeePermissions = [
            $getPermissionId('INVENTORY', 'READ_COMPONENT'),
        ];

        if ($roleEmployee) {
            foreach (array_filter($employeePermissions) as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleEmployee->id, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // === VIEWER — solo lectura ===
        if ($roleViewer) {
            foreach (array_filter($employeePermissions) as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleViewer->id, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $this->command->info('✅ Permisos de componentes asignados a roles');
    }

    private function seedCompanySetting(): void
    {
        // Insertar setting por defecto para todas las empresas existentes
        // validate_component_stock: true = bloquea consumo si no hay stock
        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            $exists = DB::table('company_settings')
                ->where('company_id', $companyId)
                ->where('key', 'validate_component_stock')
                ->exists();

            if (!$exists) {
                DB::table('company_settings')->insert([
                    'company_id'  => $companyId,
                    'key'         => 'validate_component_stock',
                    'value'       => 'true',
                    'type'        => 'boolean',
                    'description' => 'Validar disponibilidad de stock al consumir componentes en activos',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        $this->command->info('✅ CompanySetting validate_component_stock configurado');
    }
}
