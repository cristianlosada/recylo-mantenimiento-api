<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkOrdersModuleSeeder extends Seeder
{
    /**
     * Seed Work Orders module and its permissions.
     */
    public function run(): void
    {
        $this->command->info('🔧 Iniciando seeder de módulo de Órdenes de Trabajo...');

        // 1. Crear módulo
        $this->seedModule();

        // 2. Crear permisos
        $this->seedPermissions();

        // 3. Asignar permisos a roles
        $this->assignPermissionsToRoles();

        $this->command->info('✅ Módulo de Órdenes de Trabajo configurado exitosamente');
    }

    /**
     * Create Work Orders module.
     */
    private function seedModule(): void
    {
        DB::table('modules')->updateOrInsert(
            ['code' => 'WORK_ORDERS'],
            [
                'code' => 'WORK_ORDERS',
                'name' => 'Órdenes de Trabajo',
                'description' => 'Módulo CMMS para gestión de órdenes de trabajo, ejecución de mantenimiento, control de materiales y costos reales',
                'icon' => 'wrench',
                'order' => 202,
                'is_active' => true,
                'is_core' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✅ Módulo WORK_ORDERS creado');
    }

    /**
     * Create permissions for Work Orders module.
     */
    private function seedPermissions(): void
    {
        // Obtener el módulo recién creado
        $module = DB::table('modules')->where('code', 'WORK_ORDERS')->first();

        if (!$module) {
            $this->command->error('❌ Error: Módulo WORK_ORDERS no encontrado');
            return;
        }

        $permissions = [
            // CRUD BÁSICO
            [
                'code' => 'WORK_ORDERS.VIEW',
                'name' => 'Ver Órdenes de Trabajo',
                'module_id' => $module->id,
                'action' => 'view',
                'description' => 'Permite ver órdenes de trabajo asignadas',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.VIEW_ALL',
                'name' => 'Ver Todas las Órdenes',
                'module_id' => $module->id,
                'action' => 'view_all',
                'description' => 'Permite ver todas las órdenes de trabajo, incluso las no asignadas',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.CREATE',
                'name' => 'Crear Órdenes de Trabajo',
                'module_id' => $module->id,
                'action' => 'create',
                'description' => 'Permite crear nuevas órdenes de trabajo manualmente',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.UPDATE',
                'name' => 'Editar Órdenes de Trabajo',
                'module_id' => $module->id,
                'action' => 'update',
                'description' => 'Permite editar órdenes en estado draft o scheduled',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.DELETE',
                'name' => 'Eliminar Órdenes de Trabajo',
                'module_id' => $module->id,
                'action' => 'delete',
                'description' => 'Permite eliminar órdenes en estado draft',
                'is_active' => true,
            ],

            // GESTIÓN
            [
                'code' => 'WORK_ORDERS.ASSIGN',
                'name' => 'Asignar Técnicos',
                'module_id' => $module->id,
                'action' => 'assign',
                'description' => 'Permite asignar y reasignar técnicos a órdenes de trabajo',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.SCHEDULE',
                'name' => 'Programar Órdenes',
                'module_id' => $module->id,
                'action' => 'schedule',
                'description' => 'Permite programar fechas de inicio y fin de órdenes',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.CANCEL',
                'name' => 'Cancelar Órdenes',
                'module_id' => $module->id,
                'action' => 'cancel',
                'description' => 'Permite cancelar órdenes de trabajo',
                'is_active' => true,
            ],

            // EJECUCIÓN
            [
                'code' => 'WORK_ORDERS.EXECUTE',
                'name' => 'Ejecutar Órdenes',
                'module_id' => $module->id,
                'action' => 'execute',
                'description' => 'Permite iniciar, pausar y completar órdenes asignadas (técnicos)',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.VALIDATE',
                'name' => 'Validar y Cerrar',
                'module_id' => $module->id,
                'action' => 'validate',
                'description' => 'Permite validar y cerrar órdenes completadas (supervisores)',
                'is_active' => true,
            ],

            // RECURSOS
            [
                'code' => 'WORK_ORDERS.MATERIALS',
                'name' => 'Gestionar Materiales',
                'module_id' => $module->id,
                'action' => 'materials',
                'description' => 'Permite gestionar materiales y herramientas en órdenes',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.MATERIALS_REQUEST',
                'name' => 'Solicitar Materiales',
                'module_id' => $module->id,
                'action' => 'materials_request',
                'description' => 'Permite al técnico solicitar materiales al almacén',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.MATERIALS_APPROVE',
                'name' => 'Aprobar Solicitudes',
                'module_id' => $module->id,
                'action' => 'materials_approve',
                'description' => 'Permite al almacenista aprobar/rechazar solicitudes de materiales',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.MATERIALS_DELIVER',
                'name' => 'Entregar Materiales',
                'module_id' => $module->id,
                'action' => 'materials_deliver',
                'description' => 'Permite al almacenista entregar materiales con checking',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.MATERIALS_CONSUME',
                'name' => 'Registrar Consumo',
                'module_id' => $module->id,
                'action' => 'materials_consume',
                'description' => 'Permite al técnico registrar consumo real de materiales',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.MATERIALS_RETURN',
                'name' => 'Devolver Materiales',
                'module_id' => $module->id,
                'action' => 'materials_return',
                'description' => 'Permite al técnico devolver excedentes o herramientas',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.MATERIALS_RECEIVE',
                'name' => 'Recibir Devoluciones',
                'module_id' => $module->id,
                'action' => 'materials_receive',
                'description' => 'Permite al almacenista recibir y registrar devoluciones',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.TIME',
                'name' => 'Registrar Horas',
                'module_id' => $module->id,
                'action' => 'time',
                'description' => 'Permite registrar horas trabajadas en órdenes',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.EVIDENCES',
                'name' => 'Subir Evidencias',
                'module_id' => $module->id,
                'action' => 'evidences',
                'description' => 'Permite subir fotos y documentos como evidencia',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.SIGNATURE',
                'name' => 'Firmar Digitalmente',
                'module_id' => $module->id,
                'action' => 'signature',
                'description' => 'Permite firmar digitalmente al completar trabajo',
                'is_active' => true,
            ],

            // REPORTES Y COSTOS
            [
                'code' => 'WORK_ORDERS.COSTS',
                'name' => 'Ver Costos Reales',
                'module_id' => $module->id,
                'action' => 'costs',
                'description' => 'Permite ver costos reales de materiales y mano de obra',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.EXPORT',
                'name' => 'Exportar Datos',
                'module_id' => $module->id,
                'action' => 'export',
                'description' => 'Permite exportar órdenes a Excel/PDF',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.STATS',
                'name' => 'Ver Estadísticas',
                'module_id' => $module->id,
                'action' => 'stats',
                'description' => 'Permite ver estadísticas y KPIs de órdenes',
                'is_active' => true,
            ],
            [
                'code' => 'WORK_ORDERS.DELETE_ADMIN',
                'name' => 'Eliminar OT (Administrador)',
                'module_id' => $module->id,
                'action' => 'delete_admin',
                'description' => 'Permite eliminar órdenes de trabajo en cualquier estado (administrador)',
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

        $this->command->info('✅ ' . count($permissions) . ' permisos creados para WORK_ORDERS');
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
                'WORK_ORDERS.VIEW', 'WORK_ORDERS.VIEW_ALL', 'WORK_ORDERS.CREATE',
                'WORK_ORDERS.UPDATE', 'WORK_ORDERS.DELETE', 'WORK_ORDERS.DELETE_ADMIN',
                'WORK_ORDERS.ASSIGN', 'WORK_ORDERS.SCHEDULE', 'WORK_ORDERS.CANCEL',
                'WORK_ORDERS.EXECUTE', 'WORK_ORDERS.VALIDATE', 'WORK_ORDERS.MATERIALS',
                'WORK_ORDERS.MATERIALS_REQUEST', 'WORK_ORDERS.MATERIALS_APPROVE',
                'WORK_ORDERS.MATERIALS_DELIVER', 'WORK_ORDERS.MATERIALS_CONSUME',
                'WORK_ORDERS.MATERIALS_RETURN', 'WORK_ORDERS.MATERIALS_RECEIVE',
                'WORK_ORDERS.TIME', 'WORK_ORDERS.EVIDENCES', 'WORK_ORDERS.SIGNATURE',
                'WORK_ORDERS.COSTS', 'WORK_ORDERS.EXPORT', 'WORK_ORDERS.STATS',
            ],
            'Administrador' => [
                // Casi todos, excepto algunos administrativos
                'WORK_ORDERS.VIEW', 'WORK_ORDERS.VIEW_ALL', 'WORK_ORDERS.CREATE',
                'WORK_ORDERS.UPDATE', 'WORK_ORDERS.DELETE', 'WORK_ORDERS.DELETE_ADMIN',
                'WORK_ORDERS.ASSIGN', 'WORK_ORDERS.SCHEDULE', 'WORK_ORDERS.CANCEL',
                'WORK_ORDERS.EXECUTE', 'WORK_ORDERS.VALIDATE', 'WORK_ORDERS.MATERIALS',
                'WORK_ORDERS.MATERIALS_REQUEST', 'WORK_ORDERS.MATERIALS_APPROVE',
                'WORK_ORDERS.MATERIALS_DELIVER', 'WORK_ORDERS.MATERIALS_CONSUME',
                'WORK_ORDERS.MATERIALS_RETURN', 'WORK_ORDERS.MATERIALS_RECEIVE',
                'WORK_ORDERS.TIME', 'WORK_ORDERS.EVIDENCES', 'WORK_ORDERS.SIGNATURE',
                'WORK_ORDERS.COSTS', 'WORK_ORDERS.EXPORT', 'WORK_ORDERS.STATS',
            ],
            'Supervisor' => [
                // Supervisión y validación
                'WORK_ORDERS.VIEW', 'WORK_ORDERS.VIEW_ALL', 'WORK_ORDERS.CREATE',
                'WORK_ORDERS.UPDATE', 'WORK_ORDERS.ASSIGN', 'WORK_ORDERS.SCHEDULE',
                'WORK_ORDERS.VALIDATE', 'WORK_ORDERS.COSTS', 'WORK_ORDERS.STATS',
                'WORK_ORDERS.EXPORT',
            ],
            'Almacenista' => [
                // Gestión de inventario y entrega de materiales
                'WORK_ORDERS.VIEW', 'WORK_ORDERS.VIEW_ALL', 
                'WORK_ORDERS.MATERIALS', 'WORK_ORDERS.MATERIALS_APPROVE',
                'WORK_ORDERS.MATERIALS_DELIVER', 'WORK_ORDERS.MATERIALS_RECEIVE',
            ],
            'Técnico' => [
                // Ejecución de órdenes asignadas
                'WORK_ORDERS.VIEW', 'WORK_ORDERS.EXECUTE', 'WORK_ORDERS.MATERIALS',
                'WORK_ORDERS.MATERIALS_REQUEST', 'WORK_ORDERS.MATERIALS_CONSUME',
                'WORK_ORDERS.MATERIALS_RETURN', 'WORK_ORDERS.TIME', 
                'WORK_ORDERS.EVIDENCES', 'WORK_ORDERS.SIGNATURE',
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
