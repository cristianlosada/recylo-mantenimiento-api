<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryModuleSeeder extends Seeder
{
    /**
     * Seed Inventory & Warehouse module and its permissions.
     */
    public function run(): void
    {
        $this->command->info('🏭 Iniciando seeder de módulo de Inventario y Almacenes...');

        // 1. Crear módulo
        $this->seedModule();

        // 2. Crear permisos
        $this->seedPermissions();

        // 3. Asignar permisos a roles
        $this->assignPermissionsToRoles();

        $this->command->info('✅ Módulo de Inventario y Almacenes configurado exitosamente');
    }

    /**
     * Create Inventory module.
     */
    private function seedModule(): void
    {
        DB::table('modules')->updateOrInsert(
            ['code' => 'INVENTORY'],
            [
                'code' => 'INVENTORY',
                'name' => 'Inventario y Almacenes',
                'description' => 'Módulo CMMS para gestión de materiales, repuestos, stock, movimientos de inventario y almacenes',
                'icon' => 'warehouse',
                'order' => 202,
                'is_active' => true,
                'is_core' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✅ Módulo INVENTORY creado');
    }

    /**
     * Create permissions for Inventory module.
     */
    private function seedPermissions(): void
    {
        // Obtener el módulo recién creado
        $module = DB::table('modules')->where('code', 'INVENTORY')->first();

        if (!$module) {
            $this->command->error('❌ No se pudo encontrar el módulo INVENTORY');
            return;
        }

        $permissions = [
            // === ALMACENES ===
            [
                'module' => 'INVENTORY',
                'action' => 'CREATE_WAREHOUSE',
                'description' => 'Crear almacenes',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'READ_WAREHOUSE',
                'description' => 'Consultar almacenes',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'UPDATE_WAREHOUSE',
                'description' => 'Actualizar almacenes',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'DELETE_WAREHOUSE',
                'description' => 'Eliminar almacenes',
            ],

            // === MATERIALES ===
            [
                'module' => 'INVENTORY',
                'action' => 'CREATE_MATERIAL',
                'description' => 'Crear materiales y repuestos',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'READ_MATERIAL',
                'description' => 'Consultar materiales y repuestos',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'UPDATE_MATERIAL',
                'description' => 'Actualizar materiales y repuestos',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'DELETE_MATERIAL',
                'description' => 'Eliminar materiales y repuestos',
            ],

            // === CATEGORÍAS ===
            [
                'module' => 'INVENTORY',
                'action' => 'MANAGE_CATEGORIES',
                'description' => 'Gestionar categorías de materiales',
            ],

            // === STOCK ===
            [
                'module' => 'INVENTORY',
                'action' => 'VIEW_STOCK',
                'description' => 'Consultar stock de materiales',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'ADJUST_STOCK',
                'description' => 'Ajustar stock (entradas/salidas manuales)',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'TRANSFER_STOCK',
                'description' => 'Transferir stock entre almacenes',
            ],

            // === TRANSACCIONES ===
            [
                'module' => 'INVENTORY',
                'action' => 'VIEW_TRANSACTIONS',
                'description' => 'Ver historial de transacciones',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'CREATE_PURCHASE',
                'description' => 'Registrar compras de materiales',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'RECORD_DAMAGE',
                'description' => 'Registrar daños o pérdidas',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'APPROVE_TRANSACTIONS',
                'description' => 'Aprobar transacciones de inventario',
            ],

            // === REPORTES ===
            [
                'module' => 'INVENTORY',
                'action' => 'VIEW_VALUATION',
                'description' => 'Ver valorización de inventario',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'VIEW_STATS',
                'description' => 'Ver estadísticas de inventario',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'EXPORT',
                'description' => 'Exportar inventario y reportes',
            ],

            // === ADMINISTRACIÓN ===
            [
                'module' => 'INVENTORY',
                'action' => 'MANAGE_STOCK_RULES',
                'description' => 'Configurar reglas de stock (mínimos, máximos, reorden)',
            ],
            [
                'module' => 'INVENTORY',
                'action' => 'VIEW_ALL',
                'description' => 'Ver inventario de todos los almacenes',
            ],
        ];

        foreach ($permissions as $permission) {
            $code = $permission['module'] . '_' . $permission['action'];

            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'code' => $code,
                    'name' => $permission['description'],
                    'action' => $permission['action'],
                    'description' => $permission['description'],
                    'module_id' => $module->id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✅ 21 permisos de Inventario y Almacenes creados');
    }

    /**
     * Assign permissions to roles.
     */
    private function assignPermissionsToRoles(): void
    {
        // Obtener roles
        $roleSuperAdmin = DB::table('roles')->where('code', 'SUPER_ADMIN')->first();
        $roleAdmin = DB::table('roles')->where('code', 'ADMIN')->first();
        $roleSupervisor = DB::table('roles')->where('code', 'SUPERVISOR')->first();
        $roleWarehouseManager = DB::table('roles')->where('code', 'WAREHOUSE_MANAGER')->first();
        $roleEmployee = DB::table('roles')->where('code', 'EMPLOYEE')->first();
        $roleViewer = DB::table('roles')->where('code', 'VIEWER')->first();

        if (!$roleSuperAdmin || !$roleAdmin || !$roleSupervisor || !$roleWarehouseManager || !$roleEmployee || !$roleViewer) {
            $this->command->error('❌ No se encontraron todos los roles necesarios');
            return;
        }

        // Helper para obtener permission_id por código
        $getPermissionId = function($module, $action) {
            return DB::table('permissions')
                ->where('code', $module . '_' . $action)
                ->value('id');
        };

        // === SUPER ADMIN - Todos los permisos ===
        $superAdminPermissions = [
            $getPermissionId('INVENTORY', 'CREATE_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'READ_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'UPDATE_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'DELETE_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'CREATE_MATERIAL'),
            $getPermissionId('INVENTORY', 'READ_MATERIAL'),
            $getPermissionId('INVENTORY', 'UPDATE_MATERIAL'),
            $getPermissionId('INVENTORY', 'DELETE_MATERIAL'),
            $getPermissionId('INVENTORY', 'MANAGE_CATEGORIES'),
            $getPermissionId('INVENTORY', 'VIEW_STOCK'),
            $getPermissionId('INVENTORY', 'ADJUST_STOCK'),
            $getPermissionId('INVENTORY', 'TRANSFER_STOCK'),
            $getPermissionId('INVENTORY', 'VIEW_TRANSACTIONS'),
            $getPermissionId('INVENTORY', 'CREATE_PURCHASE'),
            $getPermissionId('INVENTORY', 'RECORD_DAMAGE'),
            $getPermissionId('INVENTORY', 'APPROVE_TRANSACTIONS'),
            $getPermissionId('INVENTORY', 'VIEW_VALUATION'),
            $getPermissionId('INVENTORY', 'VIEW_STATS'),
            $getPermissionId('INVENTORY', 'EXPORT'),
            $getPermissionId('INVENTORY', 'MANAGE_STOCK_RULES'),
            $getPermissionId('INVENTORY', 'VIEW_ALL'),
        ];

        foreach (array_filter($superAdminPermissions) as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $roleSuperAdmin->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // === ADMIN - Permisos completos de gestión ===
        $adminPermissions = [
            $getPermissionId('INVENTORY', 'CREATE_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'READ_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'UPDATE_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'DELETE_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'CREATE_MATERIAL'),
            $getPermissionId('INVENTORY', 'READ_MATERIAL'),
            $getPermissionId('INVENTORY', 'UPDATE_MATERIAL'),
            $getPermissionId('INVENTORY', 'DELETE_MATERIAL'),
            $getPermissionId('INVENTORY', 'MANAGE_CATEGORIES'),
            $getPermissionId('INVENTORY', 'VIEW_STOCK'),
            $getPermissionId('INVENTORY', 'ADJUST_STOCK'),
            $getPermissionId('INVENTORY', 'TRANSFER_STOCK'),
            $getPermissionId('INVENTORY', 'VIEW_TRANSACTIONS'),
            $getPermissionId('INVENTORY', 'CREATE_PURCHASE'),
            $getPermissionId('INVENTORY', 'RECORD_DAMAGE'),
            $getPermissionId('INVENTORY', 'APPROVE_TRANSACTIONS'),
            $getPermissionId('INVENTORY', 'VIEW_VALUATION'),
            $getPermissionId('INVENTORY', 'VIEW_STATS'),
            $getPermissionId('INVENTORY', 'EXPORT'),
            $getPermissionId('INVENTORY', 'MANAGE_STOCK_RULES'),
            $getPermissionId('INVENTORY', 'VIEW_ALL'),
        ];

        foreach (array_filter($adminPermissions) as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $roleAdmin->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // === SUPERVISOR - Gestión operativa completa ===
        $supervisorPermissions = [
            $getPermissionId('INVENTORY', 'READ_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'UPDATE_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'CREATE_MATERIAL'),
            $getPermissionId('INVENTORY', 'READ_MATERIAL'),
            $getPermissionId('INVENTORY', 'UPDATE_MATERIAL'),
            $getPermissionId('INVENTORY', 'MANAGE_CATEGORIES'),
            $getPermissionId('INVENTORY', 'VIEW_STOCK'),
            $getPermissionId('INVENTORY', 'ADJUST_STOCK'),
            $getPermissionId('INVENTORY', 'TRANSFER_STOCK'),
            $getPermissionId('INVENTORY', 'VIEW_TRANSACTIONS'),
            $getPermissionId('INVENTORY', 'CREATE_PURCHASE'),
            $getPermissionId('INVENTORY', 'RECORD_DAMAGE'),
            $getPermissionId('INVENTORY', 'APPROVE_TRANSACTIONS'),
            $getPermissionId('INVENTORY', 'VIEW_VALUATION'),
            $getPermissionId('INVENTORY', 'VIEW_STATS'),
            $getPermissionId('INVENTORY', 'EXPORT'),
            $getPermissionId('INVENTORY', 'MANAGE_STOCK_RULES'),
            $getPermissionId('INVENTORY', 'VIEW_ALL'),
        ];

        foreach (array_filter($supervisorPermissions) as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $roleSupervisor->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // === WAREHOUSE_MANAGER (Almacenista) - Gestión completa de inventario ===
        $warehouseManagerPermissions = [
            $getPermissionId('INVENTORY', 'READ_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'UPDATE_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'CREATE_MATERIAL'),
            $getPermissionId('INVENTORY', 'READ_MATERIAL'),
            $getPermissionId('INVENTORY', 'UPDATE_MATERIAL'),
            $getPermissionId('INVENTORY', 'MANAGE_CATEGORIES'),
            $getPermissionId('INVENTORY', 'VIEW_STOCK'),
            $getPermissionId('INVENTORY', 'ADJUST_STOCK'),
            $getPermissionId('INVENTORY', 'TRANSFER_STOCK'),
            $getPermissionId('INVENTORY', 'VIEW_TRANSACTIONS'),
            $getPermissionId('INVENTORY', 'CREATE_PURCHASE'),
            $getPermissionId('INVENTORY', 'RECORD_DAMAGE'),
            $getPermissionId('INVENTORY', 'APPROVE_TRANSACTIONS'),
            $getPermissionId('INVENTORY', 'VIEW_VALUATION'),
            $getPermissionId('INVENTORY', 'VIEW_STATS'),
            $getPermissionId('INVENTORY', 'EXPORT'),
            $getPermissionId('INVENTORY', 'MANAGE_STOCK_RULES'),
            $getPermissionId('INVENTORY', 'VIEW_ALL'),
        ];

        foreach (array_filter($warehouseManagerPermissions) as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $roleWarehouseManager->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // === EMPLOYEE (Técnico) - Consultar stock, registrar consumos ===
        $employeePermissions = [
            $getPermissionId('INVENTORY', 'READ_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'READ_MATERIAL'),
            $getPermissionId('INVENTORY', 'VIEW_STOCK'),
            $getPermissionId('INVENTORY', 'VIEW_TRANSACTIONS'),
        ];

        foreach (array_filter($employeePermissions) as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $roleEmployee->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // === VIEWER - Solo lectura ===
        $viewerPermissions = [
            $getPermissionId('INVENTORY', 'READ_WAREHOUSE'),
            $getPermissionId('INVENTORY', 'READ_MATERIAL'),
            $getPermissionId('INVENTORY', 'VIEW_STOCK'),
        ];

        foreach (array_filter($viewerPermissions) as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $roleViewer->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✅ Permisos asignados a roles:');
        $this->command->info("   - Super Admin: 21 permisos (todos)");
        $this->command->info("   - Admin: 21 permisos (todos)");
        $this->command->info("   - Supervisor: 18 permisos (sin delete warehouse/material)");
        $this->command->info("   - Almacenista: 18 permisos (gestión completa de inventario)");
        $this->command->info("   - Técnico: 4 permisos (consultar almacenes, materiales, stock, transacciones)");
        $this->command->info("   - Viewer: 3 permisos (solo lectura de almacenes, materiales y stock)");
    }
}
