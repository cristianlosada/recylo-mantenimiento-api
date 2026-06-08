<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaintenancePlansModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📅 Iniciando seeder de módulo PLANES DE MANTENIMIENTO...');

        // 1. Crear módulo
        $module = $this->seedModule();

        // 2. Crear permisos
        $this->seedPermissions($module);

        // 3. Asignar permisos a roles
        $this->assignPermissionsToRoles();

        $this->command->info('✅ Seeder de PLANES DE MANTENIMIENTO completado exitosamente');
    }

    /**
     * Seed the module.
     */
    private function seedModule()
    {
        $moduleData = [
            'code' => 'MAINTENANCE_PLANS',
            'name' => 'Planes de Mantenimiento',
            'description' => 'Gestión de planes de mantenimiento preventivo por tiempo, medidor o híbrido con generación automática de órdenes de trabajo',
            'icon' => 'calendar-check',
            'order' => 7,
            'is_active' => true,
            'is_core' => false,
        ];

        DB::table('modules')->updateOrInsert(
            ['code' => $moduleData['code']],
            array_merge($moduleData, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        $module = DB::table('modules')->where('code', 'MAINTENANCE_PLANS')->first();
        $this->command->info('✅ Módulo MAINTENANCE_PLANS creado/actualizado');

        return $module;
    }

    /**
     * Seed the permissions.
     */
    private function seedPermissions($module): void
    {
        $permissions = [
            // VISUALIZACIÓN
            [
                'code' => 'MAINTENANCE_PLANS.VIEW',
                'name' => 'Ver Planes Propios',
                'module_id' => $module->id,
                'action' => 'view',
                'description' => 'Permite ver planes de mantenimiento del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.VIEW_ALL',
                'name' => 'Ver Todos los Planes',
                'module_id' => $module->id,
                'action' => 'view_all',
                'description' => 'Permite ver planes de mantenimiento de todos los sitios de la empresa',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.VIEW_SITE',
                'name' => 'Ver Planes del Sitio',
                'module_id' => $module->id,
                'action' => 'view_site',
                'description' => 'Permite ver planes de mantenimiento de sitios asignados',
                'is_active' => true,
            ],

            // GESTIÓN BÁSICA
            [
                'code' => 'MAINTENANCE_PLANS.CREATE',
                'name' => 'Crear Planes',
                'module_id' => $module->id,
                'action' => 'create',
                'description' => 'Permite crear nuevos planes de mantenimiento',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.UPDATE',
                'name' => 'Actualizar Planes Propios',
                'module_id' => $module->id,
                'action' => 'update',
                'description' => 'Permite actualizar planes del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.UPDATE_ALL',
                'name' => 'Actualizar Todos los Planes',
                'module_id' => $module->id,
                'action' => 'update_all',
                'description' => 'Permite actualizar planes de todos los sitios',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.DELETE',
                'name' => 'Eliminar Planes Propios',
                'module_id' => $module->id,
                'action' => 'delete',
                'description' => 'Permite eliminar planes del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.DELETE_ALL',
                'name' => 'Eliminar Todos los Planes',
                'module_id' => $module->id,
                'action' => 'delete_all',
                'description' => 'Permite eliminar planes de todos los sitios',
                'is_active' => true,
            ],

            // ACTIVACIÓN/DESACTIVACIÓN
            [
                'code' => 'MAINTENANCE_PLANS.ACTIVATE',
                'name' => 'Activar/Desactivar Planes Propios',
                'module_id' => $module->id,
                'action' => 'activate',
                'description' => 'Permite activar/desactivar planes del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.ACTIVATE_ALL',
                'name' => 'Activar/Desactivar Todos los Planes',
                'module_id' => $module->id,
                'action' => 'activate_all',
                'description' => 'Permite activar/desactivar planes de todos los sitios',
                'is_active' => true,
            ],

            // EJECUCIÓN
            [
                'code' => 'MAINTENANCE_PLANS.EXECUTE',
                'name' => 'Ejecutar Planes Propios',
                'module_id' => $module->id,
                'action' => 'execute',
                'description' => 'Permite ejecutar manualmente planes del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.EXECUTE_ALL',
                'name' => 'Ejecutar Todos los Planes',
                'module_id' => $module->id,
                'action' => 'execute_all',
                'description' => 'Permite ejecutar manualmente planes de todos los sitios',
                'is_active' => true,
            ],

            // DASHBOARD Y REPORTES
            [
                'code' => 'MAINTENANCE_PLANS.DASHBOARD',
                'name' => 'Ver Dashboard',
                'module_id' => $module->id,
                'action' => 'dashboard',
                'description' => 'Permite ver dashboard con KPIs y estadísticas de planes',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.EXPORT',
                'name' => 'Exportar Datos',
                'module_id' => $module->id,
                'action' => 'export',
                'description' => 'Permite exportar planes y ejecuciones a Excel/PDF',
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE_PLANS.HISTORY',
                'name' => 'Ver Historial de Ejecuciones',
                'module_id' => $module->id,
                'action' => 'history',
                'description' => 'Permite ver historial completo de ejecuciones de planes',
                'is_active' => true,
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                array_merge($permission, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ ' . count($permissions) . ' permisos creados para MAINTENANCE_PLANS');
    }

    /**
     * Assign permissions to roles.
     */
    private function assignPermissionsToRoles(): void
    {
        // Mapeo de roles y sus permisos
        $rolePermissions = [
            'Super Administrador' => [
                // Todos los permisos
                'MAINTENANCE_PLANS.VIEW', 'MAINTENANCE_PLANS.VIEW_ALL', 'MAINTENANCE_PLANS.VIEW_SITE',
                'MAINTENANCE_PLANS.CREATE', 'MAINTENANCE_PLANS.UPDATE', 'MAINTENANCE_PLANS.UPDATE_ALL',
                'MAINTENANCE_PLANS.DELETE', 'MAINTENANCE_PLANS.DELETE_ALL', 'MAINTENANCE_PLANS.ACTIVATE',
                'MAINTENANCE_PLANS.ACTIVATE_ALL', 'MAINTENANCE_PLANS.EXECUTE', 'MAINTENANCE_PLANS.EXECUTE_ALL',
                'MAINTENANCE_PLANS.DASHBOARD', 'MAINTENANCE_PLANS.EXPORT', 'MAINTENANCE_PLANS.HISTORY',
            ],
            'Administrador' => [
                // Casi todos, excepto algunos globales
                'MAINTENANCE_PLANS.VIEW', 'MAINTENANCE_PLANS.VIEW_ALL', 'MAINTENANCE_PLANS.VIEW_SITE',
                'MAINTENANCE_PLANS.CREATE', 'MAINTENANCE_PLANS.UPDATE', 'MAINTENANCE_PLANS.UPDATE_ALL',
                'MAINTENANCE_PLANS.DELETE', 'MAINTENANCE_PLANS.DELETE_ALL', 'MAINTENANCE_PLANS.ACTIVATE',
                'MAINTENANCE_PLANS.ACTIVATE_ALL', 'MAINTENANCE_PLANS.EXECUTE', 'MAINTENANCE_PLANS.EXECUTE_ALL',
                'MAINTENANCE_PLANS.DASHBOARD', 'MAINTENANCE_PLANS.EXPORT', 'MAINTENANCE_PLANS.HISTORY',
            ],
            'Supervisor' => [
                // Visualización completa, gestión y ejecución limitada
                'MAINTENANCE_PLANS.VIEW', 'MAINTENANCE_PLANS.VIEW_ALL', 'MAINTENANCE_PLANS.VIEW_SITE',
                'MAINTENANCE_PLANS.CREATE', 'MAINTENANCE_PLANS.UPDATE', 'MAINTENANCE_PLANS.ACTIVATE',
                'MAINTENANCE_PLANS.EXECUTE', 'MAINTENANCE_PLANS.DASHBOARD', 'MAINTENANCE_PLANS.EXPORT',
                'MAINTENANCE_PLANS.HISTORY',
            ],
            'Técnico' => [
                // Solo visualización y ejecución de planes asignados
                'MAINTENANCE_PLANS.VIEW', 'MAINTENANCE_PLANS.EXECUTE', 'MAINTENANCE_PLANS.HISTORY',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionCodes) {
            $role = DB::table('roles')->where('name', $roleName)->first();

            if (!$role) {
                $this->command->warn("⚠️  Rol '{$roleName}' no encontrado, saltando...");
                continue;
            }

            foreach ($permissionCodes as $permissionCode) {
                $permission = DB::table('permissions')->where('code', $permissionCode)->first();

                if (!$permission) {
                    $this->command->warn("⚠️  Permiso '{$permissionCode}' no encontrado");
                    continue;
                }

                // Insertar o actualizar la relación
                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            $this->command->info("✅ Permisos asignados al rol '{$roleName}' (" . count($permissionCodes) . " permisos)");
        }
    }
}
