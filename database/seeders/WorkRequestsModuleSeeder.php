<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkRequestsModuleSeeder extends Seeder
{
    /**
     * Seed Work Requests module and its permissions.
     */
    public function run(): void
    {
        $this->command->info('🔧 Iniciando seeder de módulo de Solicitudes de Trabajo...');

        // 1. Crear módulo
        $this->seedModule();

        // 2. Crear permisos
        $this->seedPermissions();

        // 3. Asignar permisos a roles
        $this->assignPermissionsToRoles();

        $this->command->info('✅ Módulo de Solicitudes de Trabajo configurado exitosamente');
    }

    /**
     * Create Work Requests module.
     */
    private function seedModule(): void
    {
        DB::table('modules')->updateOrInsert(
            ['code' => 'WORK_REQUESTS'],
            [
                'code' => 'WORK_REQUESTS',
                'name' => 'Solicitudes de Trabajo',
                'description' => 'Módulo CMMS para gestión de solicitudes de trabajo, aprobaciones y seguimiento de requerimientos',
                'icon' => 'clipboard-list',
                'order' => 201,
                'is_active' => true,
                'is_core' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✅ Módulo WORK_REQUESTS creado');
    }

    /**
     * Create permissions for Work Requests module.
     */
    private function seedPermissions(): void
    {
        // Obtener el módulo recién creado
        $module = DB::table('modules')->where('code', 'WORK_REQUESTS')->first();

        if (!$module) {
            $this->command->error('❌ No se pudo encontrar el módulo WORK_REQUESTS');
            return;
        }

        $permissions = [
            // CRUD básico
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'CREATE',
                'description' => 'Crear solicitudes de trabajo',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'READ',
                'description' => 'Consultar solicitudes de trabajo',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'UPDATE',
                'description' => 'Actualizar solicitudes de trabajo',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'DELETE',
                'description' => 'Eliminar solicitudes de trabajo',
            ],

            // Flujo de aprobación
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'APPROVE',
                'description' => 'Aprobar solicitudes de trabajo',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'REJECT',
                'description' => 'Rechazar solicitudes de trabajo',
            ],

            // Comentarios y seguimiento
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'COMMENT',
                'description' => 'Comentar en solicitudes de trabajo',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'WATCH',
                'description' => 'Seguir/observar solicitudes de trabajo',
            ],

            // Adjuntos
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'ATTACH_FILES',
                'description' => 'Adjuntar archivos a solicitudes',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'DELETE_ATTACHMENTS',
                'description' => 'Eliminar archivos adjuntos',
            ],

            // Etiquetas
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'MANAGE_TAGS',
                'description' => 'Crear, editar y eliminar etiquetas',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'ASSIGN_TAGS',
                'description' => 'Asignar etiquetas a solicitudes',
            ],

            // Checklist
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'MANAGE_CHECKLIST_TEMPLATES',
                'description' => 'Gestionar plantillas de checklist',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'CHECK_ITEMS',
                'description' => 'Marcar items de checklist',
            ],

            // Relaciones
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'LINK_REQUESTS',
                'description' => 'Vincular solicitudes relacionadas',
            ],

            // Estadísticas y reportes
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'VIEW_STATS',
                'description' => 'Ver estadísticas de solicitudes',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'EXPORT',
                'description' => 'Exportar solicitudes',
            ],

            // Administración avanzada
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'VIEW_ALL',
                'description' => 'Ver todas las solicitudes de la empresa',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'CHANGE_PRIORITY',
                'description' => 'Cambiar prioridad de solicitudes',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'REASSIGN',
                'description' => 'Reasignar solicitudes a otros usuarios',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'DELETE_ADMIN',
                'description' => 'Eliminar solicitudes de trabajo en cualquier estado (administrador)',
            ],
            [
                'module' => 'WORK_REQUESTS',
                'action' => 'UPDATE_APPROVED',
                'description' => 'Editar solicitudes de trabajo ya aprobadas',
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

        $this->command->info('✅ 20 permisos de Solicitudes de Trabajo creados');
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
        $roleEmployee = DB::table('roles')->where('code', 'EMPLOYEE')->first();
        $roleViewer = DB::table('roles')->where('code', 'VIEWER')->first();

        if (!$roleSuperAdmin || !$roleAdmin || !$roleSupervisor || !$roleEmployee || !$roleViewer) {
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
            $getPermissionId('WORK_REQUESTS', 'CREATE'),
            $getPermissionId('WORK_REQUESTS', 'READ'),
            $getPermissionId('WORK_REQUESTS', 'UPDATE'),
            $getPermissionId('WORK_REQUESTS', 'DELETE'),
            $getPermissionId('WORK_REQUESTS', 'DELETE_ADMIN'),
            $getPermissionId('WORK_REQUESTS', 'UPDATE_APPROVED'),
            $getPermissionId('WORK_REQUESTS', 'APPROVE'),
            $getPermissionId('WORK_REQUESTS', 'REJECT'),
            $getPermissionId('WORK_REQUESTS', 'COMMENT'),
            $getPermissionId('WORK_REQUESTS', 'WATCH'),
            $getPermissionId('WORK_REQUESTS', 'ATTACH_FILES'),
            $getPermissionId('WORK_REQUESTS', 'DELETE_ATTACHMENTS'),
            $getPermissionId('WORK_REQUESTS', 'MANAGE_TAGS'),
            $getPermissionId('WORK_REQUESTS', 'ASSIGN_TAGS'),
            $getPermissionId('WORK_REQUESTS', 'MANAGE_CHECKLIST_TEMPLATES'),
            $getPermissionId('WORK_REQUESTS', 'CHECK_ITEMS'),
            $getPermissionId('WORK_REQUESTS', 'LINK_REQUESTS'),
            $getPermissionId('WORK_REQUESTS', 'VIEW_STATS'),
            $getPermissionId('WORK_REQUESTS', 'EXPORT'),
            $getPermissionId('WORK_REQUESTS', 'VIEW_ALL'),
            $getPermissionId('WORK_REQUESTS', 'CHANGE_PRIORITY'),
            $getPermissionId('WORK_REQUESTS', 'REASSIGN'),
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
            $getPermissionId('WORK_REQUESTS', 'CREATE'),
            $getPermissionId('WORK_REQUESTS', 'READ'),
            $getPermissionId('WORK_REQUESTS', 'UPDATE'),
            $getPermissionId('WORK_REQUESTS', 'DELETE'),
            $getPermissionId('WORK_REQUESTS', 'DELETE_ADMIN'),
            $getPermissionId('WORK_REQUESTS', 'UPDATE_APPROVED'),
            $getPermissionId('WORK_REQUESTS', 'APPROVE'),
            $getPermissionId('WORK_REQUESTS', 'REJECT'),
            $getPermissionId('WORK_REQUESTS', 'COMMENT'),
            $getPermissionId('WORK_REQUESTS', 'WATCH'),
            $getPermissionId('WORK_REQUESTS', 'ATTACH_FILES'),
            $getPermissionId('WORK_REQUESTS', 'DELETE_ATTACHMENTS'),
            $getPermissionId('WORK_REQUESTS', 'MANAGE_TAGS'),
            $getPermissionId('WORK_REQUESTS', 'ASSIGN_TAGS'),
            $getPermissionId('WORK_REQUESTS', 'MANAGE_CHECKLIST_TEMPLATES'),
            $getPermissionId('WORK_REQUESTS', 'CHECK_ITEMS'),
            $getPermissionId('WORK_REQUESTS', 'LINK_REQUESTS'),
            $getPermissionId('WORK_REQUESTS', 'VIEW_STATS'),
            $getPermissionId('WORK_REQUESTS', 'EXPORT'),
            $getPermissionId('WORK_REQUESTS', 'VIEW_ALL'),
            $getPermissionId('WORK_REQUESTS', 'CHANGE_PRIORITY'),
            $getPermissionId('WORK_REQUESTS', 'REASSIGN'),
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

        // === SUPERVISOR - Aprobación y gestión operativa ===
        $supervisorPermissions = [
            $getPermissionId('WORK_REQUESTS', 'CREATE'),
            $getPermissionId('WORK_REQUESTS', 'READ'),
            $getPermissionId('WORK_REQUESTS', 'UPDATE'),
            $getPermissionId('WORK_REQUESTS', 'APPROVE'),
            $getPermissionId('WORK_REQUESTS', 'REJECT'),
            $getPermissionId('WORK_REQUESTS', 'COMMENT'),
            $getPermissionId('WORK_REQUESTS', 'WATCH'),
            $getPermissionId('WORK_REQUESTS', 'ATTACH_FILES'),
            $getPermissionId('WORK_REQUESTS', 'ASSIGN_TAGS'),
            $getPermissionId('WORK_REQUESTS', 'CHECK_ITEMS'),
            $getPermissionId('WORK_REQUESTS', 'LINK_REQUESTS'),
            $getPermissionId('WORK_REQUESTS', 'VIEW_STATS'),
            $getPermissionId('WORK_REQUESTS', 'EXPORT'),
            $getPermissionId('WORK_REQUESTS', 'VIEW_ALL'),
            $getPermissionId('WORK_REQUESTS', 'CHANGE_PRIORITY'),
            $getPermissionId('WORK_REQUESTS', 'REASSIGN'),
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

        // === EMPLOYEE (Técnico) - Crear, leer, comentar, checklist ===
        $employeePermissions = [
            $getPermissionId('WORK_REQUESTS', 'CREATE'),
            $getPermissionId('WORK_REQUESTS', 'READ'),
            $getPermissionId('WORK_REQUESTS', 'COMMENT'),
            $getPermissionId('WORK_REQUESTS', 'WATCH'),
            $getPermissionId('WORK_REQUESTS', 'ATTACH_FILES'),
            $getPermissionId('WORK_REQUESTS', 'CHECK_ITEMS'),
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
            $getPermissionId('WORK_REQUESTS', 'READ'),
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
        $this->command->info("   - Super Admin: 20 permisos (todos)");
        $this->command->info("   - Admin: 20 permisos (todos)");
        $this->command->info("   - Supervisor: 16 permisos (sin delete, manage tags/templates)");
        $this->command->info("   - Técnico: 6 permisos (crear, leer, comentar, adjuntar, checklist)");
        $this->command->info("   - Viewer: 1 permiso (solo lectura)");
    }
}
