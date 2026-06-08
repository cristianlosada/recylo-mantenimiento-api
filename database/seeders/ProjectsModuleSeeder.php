<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectsModuleSeeder extends Seeder
{
    /**
     * Seed del módulo Proyectos y Montajes.
     *
     * Incluye:
     *  1. Catálogos auxiliares (sin enum)
     *  2. Módulo en tabla modules
     *  3. Permisos PROJECTS.*
     *  4. Asignación de permisos a roles del sistema
     */
    public function run(): void
    {
        $this->command->info('📋 Iniciando seeder del módulo Proyectos y Montajes...');

        $this->seedCatalogs();
        $this->seedModule();
        $this->seedPermissions();
        $this->assignPermissionsToRoles();

        $this->command->info('✅ Módulo Proyectos y Montajes configurado exitosamente');
    }

    // =========================================================================
    // 1. CATÁLOGOS AUXILIARES
    // =========================================================================

    private function seedCatalogs(): void
    {
        // --- project_statuses ---
        $statuses = [
            ['code' => 'draft',            'name' => 'Borrador',               'color' => 'gray',   'is_terminal' => false],
            ['code' => 'pending_approval', 'name' => 'Pendiente de aprobación','color' => 'yellow', 'is_terminal' => false],
            ['code' => 'approved',         'name' => 'Aprobado',               'color' => 'blue',   'is_terminal' => false],
            ['code' => 'in_progress',      'name' => 'En ejecución',           'color' => 'green',  'is_terminal' => false],
            ['code' => 'paused',           'name' => 'En pausa',               'color' => 'orange', 'is_terminal' => false],
            ['code' => 'finished',         'name' => 'Finalizado',             'color' => 'teal',   'is_terminal' => false],
            ['code' => 'closed',           'name' => 'Cerrado',                'color' => 'indigo', 'is_terminal' => true],
            ['code' => 'cancelled',        'name' => 'Cancelado',              'color' => 'red',    'is_terminal' => true],
        ];

        foreach ($statuses as $s) {
            DB::table('project_statuses')->updateOrInsert(
                ['code' => $s['code']],
                array_merge($s, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        // --- project_types ---
        $types = [
            ['code' => 'investment',           'name' => 'Proyecto de inversión',        'code_prefix' => 'PROY', 'icon' => 'chart-line'],
            ['code' => 'mechanical_assembly',  'name' => 'Montaje mecánico',             'code_prefix' => 'MMEC', 'icon' => 'cogs'],
            ['code' => 'electrical_assembly',  'name' => 'Montaje eléctrico',            'code_prefix' => 'MELE', 'icon' => 'bolt'],
            ['code' => 'civil_assembly',       'name' => 'Montaje civil',                'code_prefix' => 'MCIV', 'icon' => 'hard-hat'],
            ['code' => 'infrastructure',       'name' => 'Adecuación de infraestructura','code_prefix' => 'ADEC', 'icon' => 'building'],
            ['code' => 'process_improvement',  'name' => 'Mejora de proceso',            'code_prefix' => 'MEJO', 'icon' => 'arrow-up'],
            ['code' => 'internal_fabrication', 'name' => 'Fabricación interna',          'code_prefix' => 'FAB',  'icon' => 'hammer'],
            ['code' => 'dismantling',          'name' => 'Desmontaje / traslado',        'code_prefix' => 'DESM', 'icon' => 'truck-moving'],
            ['code' => 'special_inspection',   'name' => 'Inspección especial',          'code_prefix' => 'INSP', 'icon' => 'search'],
            ['code' => 'internal_support',     'name' => 'Apoyo técnico interno',        'code_prefix' => 'APOY', 'icon' => 'hands-helping'],
            ['code' => 'other',                'name' => 'Otro',                         'code_prefix' => 'OTR',  'icon' => 'ellipsis-h'],
        ];

        foreach ($types as $t) {
            DB::table('project_types')->updateOrInsert(
                ['code' => $t['code']],
                array_merge($t, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        // --- project_phase_statuses ---
        $phaseStatuses = [
            ['code' => 'pending',     'name' => 'Pendiente',    'color' => 'gray'],
            ['code' => 'in_progress', 'name' => 'En ejecución', 'color' => 'blue'],
            ['code' => 'completed',   'name' => 'Completada',   'color' => 'green'],
        ];

        foreach ($phaseStatuses as $s) {
            DB::table('project_phase_statuses')->updateOrInsert(
                ['code' => $s['code']],
                array_merge($s, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        // --- project_member_roles ---
        $memberRoles = [
            ['code' => 'leader',     'name' => 'Líder'],
            ['code' => 'supervisor', 'name' => 'Supervisor'],
            ['code' => 'technician', 'name' => 'Técnico'],
            ['code' => 'consultant', 'name' => 'Consultor'],
        ];

        foreach ($memberRoles as $r) {
            DB::table('project_member_roles')->updateOrInsert(
                ['code' => $r['code']],
                array_merge($r, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        // --- project_log_statuses ---
        $logStatuses = [
            ['code' => 'registered', 'name' => 'Registrado', 'color' => 'gray'],
            ['code' => 'reviewed',   'name' => 'Revisado',   'color' => 'yellow'],
            ['code' => 'validated',  'name' => 'Validado',   'color' => 'green'],
        ];

        foreach ($logStatuses as $s) {
            DB::table('project_log_statuses')->updateOrInsert(
                ['code' => $s['code']],
                array_merge($s, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        // --- project_attachment_types ---
        $attachmentTypes = [
            ['code' => 'photo',    'name' => 'Fotografía'],
            ['code' => 'document', 'name' => 'Documento'],
            ['code' => 'other',    'name' => 'Otro'],
        ];

        foreach ($attachmentTypes as $t) {
            DB::table('project_attachment_types')->updateOrInsert(
                ['code' => $t['code']],
                array_merge($t, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        $this->command->info('✅ Catálogos de Proyectos creados');
    }

    // =========================================================================
    // 2. MÓDULO
    // =========================================================================

    private function seedModule(): void
    {
        DB::table('modules')->updateOrInsert(
            ['code' => 'PROJECTS'],
            [
                'code'        => 'PROJECTS',
                'name'        => 'Proyectos y Montajes',
                'description' => 'Planeación, ejecución, seguimiento y cierre de proyectos, montajes, mejoras y actividades técnicas del personal de mantenimiento',
                'icon'        => 'project-diagram',
                'order'       => 300,
                'is_active'   => true,
                'is_core'     => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        $this->command->info('✅ Módulo PROJECTS creado');
    }

    // =========================================================================
    // 3. PERMISOS
    // =========================================================================

    private function seedPermissions(): void
    {
        $module = DB::table('modules')->where('code', 'PROJECTS')->first();

        if (!$module) {
            $this->command->error('❌ Módulo PROJECTS no encontrado');
            return;
        }

        $permissions = [
            // CONSULTA
            [
                'code'        => 'PROJECTS.VIEW',
                'name'        => 'Ver proyectos asignados',
                'action'      => 'view',
                'description' => 'Ver proyectos en los que el usuario es miembro',
            ],
            [
                'code'        => 'PROJECTS.VIEW_ALL',
                'name'        => 'Ver todos los proyectos',
                'action'      => 'view_all',
                'description' => 'Ver todos los proyectos de la empresa sin importar asignación',
            ],

            // CRUD BÁSICO
            [
                'code'        => 'PROJECTS.CREATE',
                'name'        => 'Crear proyectos',
                'action'      => 'create',
                'description' => 'Crear nuevos proyectos o montajes',
            ],
            [
                'code'        => 'PROJECTS.UPDATE',
                'name'        => 'Editar proyectos',
                'action'      => 'update',
                'description' => 'Editar información general de proyectos en estado draft o approved',
            ],
            [
                'code'        => 'PROJECTS.DELETE',
                'name'        => 'Eliminar proyectos',
                'action'      => 'delete',
                'description' => 'Eliminar proyectos en estado draft (soft delete)',
            ],

            // CICLO DE VIDA
            [
                'code'        => 'PROJECTS.APPROVE',
                'name'        => 'Aprobar proyectos',
                'action'      => 'approve',
                'description' => 'Aprobar proyectos en estado pending_approval (si projects.requires_approval=true)',
            ],
            [
                'code'        => 'PROJECTS.CLOSE',
                'name'        => 'Cerrar proyectos',
                'action'      => 'close',
                'description' => 'Cerrar proyectos finalizados con observaciones y lecciones aprendidas',
            ],

            // PLANEACIÓN
            [
                'code'        => 'PROJECTS.MANAGE_PHASES',
                'name'        => 'Gestionar fases',
                'action'      => 'manage_phases',
                'description' => 'Crear, editar y reordenar fases del proyecto',
            ],
            [
                'code'        => 'PROJECTS.MANAGE_MEMBERS',
                'name'        => 'Gestionar miembros',
                'action'      => 'manage_members',
                'description' => 'Asignar y retirar miembros del equipo del proyecto',
            ],

            // BITÁCORA / PDT
            [
                'code'        => 'PROJECTS.LOG_OWN',
                'name'        => 'Registrar bitácora propia',
                'action'      => 'log_own',
                'description' => 'Registrar actividades diarias propias en un proyecto asignado',
            ],
            [
                'code'        => 'PROJECTS.LOG_TEAM',
                'name'        => 'Registrar bitácora de cuadrilla',
                'action'      => 'log_team',
                'description' => 'Registrar actividades en nombre de otro miembro del equipo (requiere projects.allow_team_log=true)',
            ],
            [
                'code'        => 'PROJECTS.REVIEW_LOG',
                'name'        => 'Revisar bitácoras',
                'action'      => 'review_log',
                'description' => 'Marcar registros de bitácora como revisados',
            ],
            [
                'code'        => 'PROJECTS.VALIDATE_LOG',
                'name'        => 'Validar bitácoras',
                'action'      => 'validate_log',
                'description' => 'Validar definitivamente registros de bitácora',
            ],

            // COSTOS Y REPORTES
            [
                'code'        => 'PROJECTS.VIEW_COSTS',
                'name'        => 'Ver costos del proyecto',
                'action'      => 'view_costs',
                'description' => 'Ver presupuesto, costo real y comparativo presupuesto vs real',
            ],
            [
                'code'        => 'PROJECTS.MANAGE_WAREHOUSE',
                'name'        => 'Vincular salidas de almacén',
                'action'      => 'manage_warehouse',
                'description' => 'Asociar salidas de inventario al proyecto (requiere projects.warehouse_integration_enabled=true)',
            ],
            [
                'code'        => 'PROJECTS.EXPORT',
                'name'        => 'Exportar reportes',
                'action'      => 'export',
                'description' => 'Exportar bitácoras, resúmenes y reportes a PDF/Excel',
            ],
            [
                'code'        => 'PROJECTS.UPDATE_PROGRESS',
                'name'        => 'Actualizar avance de fase',
                'action'      => 'update_progress',
                'description' => 'Actualizar el porcentaje de avance de las fases del proyecto (Planeadora, Administradora, Líder)',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                array_merge($permission, [
                    'module_id'  => $module->id,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ ' . count($permissions) . ' permisos creados para PROJECTS');
    }

    // =========================================================================
    // 4. ASIGNACIÓN DE PERMISOS A ROLES
    // =========================================================================

    private function assignPermissionsToRoles(): void
    {
        $rolePermissions = [
            'Super Administrador' => [
                'PROJECTS.VIEW', 'PROJECTS.VIEW_ALL', 'PROJECTS.CREATE', 'PROJECTS.UPDATE',
                'PROJECTS.DELETE', 'PROJECTS.APPROVE', 'PROJECTS.CLOSE',
                'PROJECTS.MANAGE_PHASES', 'PROJECTS.MANAGE_MEMBERS',
                'PROJECTS.LOG_OWN', 'PROJECTS.LOG_TEAM',
                'PROJECTS.REVIEW_LOG', 'PROJECTS.VALIDATE_LOG',
                'PROJECTS.VIEW_COSTS', 'PROJECTS.MANAGE_WAREHOUSE', 'PROJECTS.EXPORT',
                'PROJECTS.UPDATE_PROGRESS',
            ],
            'Administrador' => [
                'PROJECTS.VIEW', 'PROJECTS.VIEW_ALL', 'PROJECTS.CREATE', 'PROJECTS.UPDATE',
                'PROJECTS.DELETE', 'PROJECTS.APPROVE', 'PROJECTS.CLOSE',
                'PROJECTS.MANAGE_PHASES', 'PROJECTS.MANAGE_MEMBERS',
                'PROJECTS.LOG_OWN', 'PROJECTS.LOG_TEAM',
                'PROJECTS.REVIEW_LOG', 'PROJECTS.VALIDATE_LOG',
                'PROJECTS.VIEW_COSTS', 'PROJECTS.MANAGE_WAREHOUSE', 'PROJECTS.EXPORT',
                'PROJECTS.UPDATE_PROGRESS',
            ],
            'Supervisor' => [
                'PROJECTS.VIEW', 'PROJECTS.VIEW_ALL',
                'PROJECTS.MANAGE_MEMBERS',
                'PROJECTS.LOG_OWN', 'PROJECTS.LOG_TEAM',
                'PROJECTS.REVIEW_LOG',
                'PROJECTS.VIEW_COSTS', 'PROJECTS.EXPORT',
                'PROJECTS.UPDATE_PROGRESS',
            ],
            'Técnico' => [
                'PROJECTS.VIEW',
                'PROJECTS.LOG_OWN',
            ],
            'Consulta' => [
                'PROJECTS.VIEW', 'PROJECTS.VIEW_ALL',
                'PROJECTS.VIEW_COSTS',
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

                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $role->id, 'permission_id' => $permission->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            $this->command->info("✅ Permisos asignados a '{$roleName}' (" . count($permissionCodes) . ')');
        }
    }
}
