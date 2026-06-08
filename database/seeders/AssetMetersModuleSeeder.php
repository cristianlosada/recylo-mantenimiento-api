<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetMetersModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📊 Iniciando seeder de módulo MEDIDORES DE ACTIVOS...');

        // 1. Crear módulo
        $module = $this->seedModule();

        // 2. Crear permisos
        $this->seedPermissions($module);

        // 3. Asignar permisos a roles
        $this->assignPermissionsToRoles();

        $this->command->info('✅ Seeder de MEDIDORES DE ACTIVOS completado exitosamente');
    }

    /**
     * Seed the module.
     */
    private function seedModule()
    {
        $moduleData = [
            'code' => 'ASSET_METERS',
            'name' => 'Medidores de Activos',
            'description' => 'Gestión de medidores y lecturas de activos (horómetros, odómetros, contadores de ciclos)',
            'icon' => 'gauge-high',
            'order' => 6,
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

        $module = DB::table('modules')->where('code', 'ASSET_METERS')->first();
        $this->command->info('✅ Módulo ASSET_METERS creado/actualizado');

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
                'code' => 'ASSET_METERS.VIEW',
                'name' => 'Ver Medidores Propios',
                'module_id' => $module->id,
                'action' => 'view',
                'description' => 'Permite ver medidores de activos del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.VIEW_ALL',
                'name' => 'Ver Todos los Medidores',
                'module_id' => $module->id,
                'action' => 'view_all',
                'description' => 'Permite ver medidores de activos de todos los sitios de la empresa',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.VIEW_SITE',
                'name' => 'Ver Medidores del Sitio',
                'module_id' => $module->id,
                'action' => 'view_site',
                'description' => 'Permite ver medidores de activos de sitios asignados',
                'is_active' => true,
            ],

            // GESTIÓN BÁSICA
            [
                'code' => 'ASSET_METERS.CREATE',
                'name' => 'Crear Medidores',
                'module_id' => $module->id,
                'action' => 'create',
                'description' => 'Permite crear nuevos medidores para activos',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.UPDATE',
                'name' => 'Actualizar Medidores Propios',
                'module_id' => $module->id,
                'action' => 'update',
                'description' => 'Permite actualizar medidores del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.UPDATE_ALL',
                'name' => 'Actualizar Todos los Medidores',
                'module_id' => $module->id,
                'action' => 'update_all',
                'description' => 'Permite actualizar medidores de todos los sitios',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.DELETE',
                'name' => 'Eliminar Medidores Propios',
                'module_id' => $module->id,
                'action' => 'delete',
                'description' => 'Permite eliminar medidores del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.DELETE_ALL',
                'name' => 'Eliminar Todos los Medidores',
                'module_id' => $module->id,
                'action' => 'delete_all',
                'description' => 'Permite eliminar medidores de todos los sitios',
                'is_active' => true,
            ],

            // ACTIVACIÓN/DESACTIVACIÓN
            [
                'code' => 'ASSET_METERS.ACTIVATE',
                'name' => 'Activar/Desactivar Medidores Propios',
                'module_id' => $module->id,
                'action' => 'activate',
                'description' => 'Permite activar/desactivar medidores del mismo sitio',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.ACTIVATE_ALL',
                'name' => 'Activar/Desactivar Todos los Medidores',
                'module_id' => $module->id,
                'action' => 'activate_all',
                'description' => 'Permite activar/desactivar medidores de todos los sitios',
                'is_active' => true,
            ],

            // LECTURAS
            [
                'code' => 'ASSET_METERS.RECORD_READING',
                'name' => 'Registrar Lecturas',
                'module_id' => $module->id,
                'action' => 'record_reading',
                'description' => 'Permite registrar nuevas lecturas en medidores',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.VIEW_READINGS',
                'name' => 'Ver Historial de Lecturas',
                'module_id' => $module->id,
                'action' => 'view_readings',
                'description' => 'Permite ver historial completo de lecturas de medidores',
                'is_active' => true,
            ],

            // ESTADÍSTICAS Y REPORTES
            [
                'code' => 'ASSET_METERS.STATISTICS',
                'name' => 'Ver Estadísticas',
                'module_id' => $module->id,
                'action' => 'statistics',
                'description' => 'Permite ver estadísticas y proyecciones de medidores',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.EXPORT',
                'name' => 'Exportar Datos',
                'module_id' => $module->id,
                'action' => 'export',
                'description' => 'Permite exportar medidores y lecturas a Excel/PDF',
                'is_active' => true,
            ],
            [
                'code' => 'ASSET_METERS.IMPORT',
                'name' => 'Importar Lecturas',
                'module_id' => $module->id,
                'action' => 'import',
                'description' => 'Permite importar lecturas masivas desde Excel',
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

        $this->command->info('✅ ' . count($permissions) . ' permisos creados para ASSET_METERS');
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
                'ASSET_METERS.VIEW', 'ASSET_METERS.VIEW_ALL', 'ASSET_METERS.VIEW_SITE',
                'ASSET_METERS.CREATE', 'ASSET_METERS.UPDATE', 'ASSET_METERS.UPDATE_ALL',
                'ASSET_METERS.DELETE', 'ASSET_METERS.DELETE_ALL', 'ASSET_METERS.ACTIVATE',
                'ASSET_METERS.ACTIVATE_ALL', 'ASSET_METERS.RECORD_READING',
                'ASSET_METERS.VIEW_READINGS', 'ASSET_METERS.STATISTICS',
                'ASSET_METERS.EXPORT', 'ASSET_METERS.IMPORT',
            ],
            'Administrador' => [
                // Casi todos, excepto algunos globales
                'ASSET_METERS.VIEW', 'ASSET_METERS.VIEW_ALL', 'ASSET_METERS.VIEW_SITE',
                'ASSET_METERS.CREATE', 'ASSET_METERS.UPDATE', 'ASSET_METERS.UPDATE_ALL',
                'ASSET_METERS.DELETE', 'ASSET_METERS.DELETE_ALL', 'ASSET_METERS.ACTIVATE',
                'ASSET_METERS.ACTIVATE_ALL', 'ASSET_METERS.RECORD_READING',
                'ASSET_METERS.VIEW_READINGS', 'ASSET_METERS.STATISTICS',
                'ASSET_METERS.EXPORT', 'ASSET_METERS.IMPORT',
            ],
            'Supervisor' => [
                // Visualización completa, gestión limitada
                'ASSET_METERS.VIEW', 'ASSET_METERS.VIEW_ALL', 'ASSET_METERS.VIEW_SITE',
                'ASSET_METERS.CREATE', 'ASSET_METERS.UPDATE', 'ASSET_METERS.RECORD_READING',
                'ASSET_METERS.VIEW_READINGS', 'ASSET_METERS.STATISTICS', 'ASSET_METERS.EXPORT',
            ],
            'Técnico' => [
                // Solo registro de lecturas y visualización
                'ASSET_METERS.VIEW', 'ASSET_METERS.RECORD_READING', 'ASSET_METERS.VIEW_READINGS',
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
