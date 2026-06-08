<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Eliminar roles obsoletos
        DB::table('roles')->whereIn('code', ['QUALITY_MANAGER', 'AUDITOR'])->delete();

        // Roles del sistema
        $roles = [
            [
                'code' => 'SUPER_ADMIN',
                'name' => 'Super Administrador',
                'description' => 'Administrador global del sistema',
                'company_id' => null, // Rol del sistema
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'ADMIN',
                'name' => 'Administrador',
                'description' => 'Administrador de empresa',
                'company_id' => null, // Template, se copia por empresa
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SUPERVISOR',
                'name' => 'Supervisor',
                'description' => 'Supervisor de área o proceso',
                'company_id' => null,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'WAREHOUSE_MANAGER',
                'name' => 'Almacenista',
                'description' => 'Encargado de almacén - gestiona inventario y entrega de materiales',
                'company_id' => null,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EMPLOYEE',
                'name' => 'Técnico',
                'description' => 'Empleado técnico',
                'company_id' => null,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'OPERATOR',
                'name' => 'Operario',
                'description' => 'Operario de planta - acceso básico al sistema',
                'company_id' => null,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'VIEWER',
                'name' => 'Consulta',
                'description' => 'Solo puede consultar información',
                'company_id' => null,
                'is_system' => true,
                'is_active' => true,
            ]
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['code' => $role['code']], // condition to check
                [
                    ...$role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Permisos del sistema
        $permissions = [
            // Gestión de usuarios
            ['module' => 'USERS', 'action' => 'CREATE', 'description' => 'Crear usuarios'],
            ['module' => 'USERS', 'action' => 'READ', 'description' => 'Consultar usuarios'],
            ['module' => 'USERS', 'action' => 'UPDATE', 'description' => 'Actualizar usuarios'],
            ['module' => 'USERS', 'action' => 'DELETE', 'description' => 'Eliminar usuarios'],
            ['module' => 'USERS', 'action' => 'ASSIGN_ROLES', 'description' => 'Asignar roles a usuarios'],
            ['module' => 'USERS', 'action' => 'MANAGE_SALARY', 'description' => 'Ver y editar tarifa horaria y salario de usuarios'],

            // Gestión de roles
            ['module' => 'ROLES', 'action' => 'CREATE', 'description' => 'Crear roles'],
            ['module' => 'ROLES', 'action' => 'READ', 'description' => 'Consultar roles'],
            ['module' => 'ROLES', 'action' => 'UPDATE', 'description' => 'Actualizar roles'],
            ['module' => 'ROLES', 'action' => 'DELETE', 'description' => 'Eliminar roles'],
            ['module' => 'ROLES', 'action' => 'ASSIGN_PERMISSIONS', 'description' => 'Asignar permisos a roles'],

            // Gestión de delegaciones
            ['module' => 'DELEGATIONS', 'action' => 'CREATE', 'description' => 'Crear delegaciones de roles'],
            ['module' => 'DELEGATIONS', 'action' => 'READ', 'description' => 'Consultar delegaciones'],
            ['module' => 'DELEGATIONS', 'action' => 'UPDATE', 'description' => 'Actualizar delegaciones'],
            ['module' => 'DELEGATIONS', 'action' => 'DELETE', 'description' => 'Eliminar delegaciones'],
            ['module' => 'DELEGATIONS', 'action' => 'REVOKE', 'description' => 'Revocar delegaciones'],

            // Gestión de módulos
            ['module' => 'MODULES', 'action' => 'CREATE', 'description' => 'Crear módulos'],
            ['module' => 'MODULES', 'action' => 'READ', 'description' => 'Consultar módulos'],
            ['module' => 'MODULES', 'action' => 'UPDATE', 'description' => 'Actualizar módulos'],
            ['module' => 'MODULES', 'action' => 'DELETE', 'description' => 'Eliminar módulos'],
            ['module' => 'MODULES', 'action' => 'ENABLE', 'description' => 'Activar/Desactivar módulos'],

            // Gestión de empresas
            ['module' => 'COMPANIES', 'action' => 'CREATE', 'description' => 'Crear empresas'],
            ['module' => 'COMPANIES', 'action' => 'READ', 'description' => 'Consultar empresas'],
            ['module' => 'COMPANIES', 'action' => 'UPDATE', 'description' => 'Actualizar empresas'],
            ['module' => 'COMPANIES', 'action' => 'DELETE', 'description' => 'Eliminar empresas'],

            // Gestión de sedes de empresa
            ['module' => 'COMPANY_SITES', 'action' => 'CREATE', 'description' => 'Crear sedes de empresa'],
            ['module' => 'COMPANY_SITES', 'action' => 'READ', 'description' => 'Consultar sedes de empresa'],
            ['module' => 'COMPANY_SITES', 'action' => 'UPDATE', 'description' => 'Actualizar sedes de empresa'],
            ['module' => 'COMPANY_SITES', 'action' => 'DELETE', 'description' => 'Eliminar sedes de empresa'],

            // Gestión de activos (CMMS)
            ['module' => 'ASSETS', 'action' => 'CREATE', 'description' => 'Crear activos'],
            ['module' => 'ASSETS', 'action' => 'READ', 'description' => 'Consultar activos'],
            ['module' => 'ASSETS', 'action' => 'UPDATE', 'description' => 'Actualizar activos'],
            ['module' => 'ASSETS', 'action' => 'DELETE', 'description' => 'Eliminar activos'],
            ['module' => 'ASSETS', 'action' => 'ASSIGN_USERS', 'description' => 'Asignar usuarios responsables a activos'],
            ['module' => 'ASSETS', 'action' => 'MANAGE_SPECS', 'description' => 'Gestionar especificaciones técnicas'],
            ['module' => 'ASSETS', 'action' => 'GENERATE_QR', 'description' => 'Generar códigos QR'],
            ['module' => 'ASSETS', 'action' => 'EXPORT', 'description' => 'Exportar fichas técnicas y reportes'],
            ['module' => 'ASSETS', 'action' => 'IMPORT', 'description' => 'Importar activos masivamente'],
            ['module' => 'ASSETS', 'action' => 'VIEW_STATS', 'description' => 'Ver estadísticas de activos'],

            // Reportes
            ['module' => 'REPORTS', 'action' => 'VIEW_BASIC', 'description' => 'Ver reportes básicos'],
            ['module' => 'REPORTS', 'action' => 'VIEW_ADVANCED', 'description' => 'Ver reportes avanzados'],
            ['module' => 'REPORTS', 'action' => 'EXPORT', 'description' => 'Exportar reportes'],

            // Sistema
            ['module' => 'SYSTEM', 'action' => 'SETTINGS', 'description' => 'Configurar sistema'],
            ['module' => 'SYSTEM', 'action' => 'AUDIT_LOGS', 'description' => 'Ver logs de auditoría'],
            ['module' => 'SYSTEM', 'action' => 'BACKUP', 'description' => 'Realizar respaldos'],

            // Gestión de Cargos laborales
            ['module' => 'JOB_POSITIONS', 'action' => 'CREATE', 'description' => 'Crear cargos'],
            ['module' => 'JOB_POSITIONS', 'action' => 'READ',   'description' => 'Consultar cargos'],
            ['module' => 'JOB_POSITIONS', 'action' => 'UPDATE', 'description' => 'Actualizar cargos'],
            ['module' => 'JOB_POSITIONS', 'action' => 'DELETE', 'description' => 'Eliminar cargos'],

            // Gestión de Áreas (líneas de producción)
            ['module' => 'PRODUCTION_LINES', 'action' => 'CREATE', 'description' => 'Crear áreas'],
            ['module' => 'PRODUCTION_LINES', 'action' => 'READ',   'description' => 'Consultar áreas'],
            ['module' => 'PRODUCTION_LINES', 'action' => 'UPDATE', 'description' => 'Actualizar áreas'],
            ['module' => 'PRODUCTION_LINES', 'action' => 'DELETE', 'description' => 'Eliminar áreas'],

            // Gestión de Categorías de Activos
            ['module' => 'ASSET_CATEGORIES', 'action' => 'CREATE', 'description' => 'Crear categorías de activos'],
            ['module' => 'ASSET_CATEGORIES', 'action' => 'READ',   'description' => 'Consultar categorías de activos'],
            ['module' => 'ASSET_CATEGORIES', 'action' => 'UPDATE', 'description' => 'Actualizar categorías de activos'],
            ['module' => 'ASSET_CATEGORIES', 'action' => 'DELETE', 'description' => 'Eliminar categorías de activos'],

            // Gestión de Sistemas de Activos
            ['module' => 'ASSET_SYSTEMS', 'action' => 'CREATE', 'description' => 'Crear sistemas de activos'],
            ['module' => 'ASSET_SYSTEMS', 'action' => 'READ',   'description' => 'Consultar sistemas de activos'],
            ['module' => 'ASSET_SYSTEMS', 'action' => 'UPDATE', 'description' => 'Actualizar sistemas de activos'],
            ['module' => 'ASSET_SYSTEMS', 'action' => 'DELETE', 'description' => 'Eliminar sistemas de activos'],

            // Gestión de Tipos de Mantenimiento
            ['module' => 'MAINTENANCE_TYPES', 'action' => 'CREATE', 'description' => 'Crear tipos de mantenimiento'],
            ['module' => 'MAINTENANCE_TYPES', 'action' => 'READ',   'description' => 'Consultar tipos de mantenimiento'],
            ['module' => 'MAINTENANCE_TYPES', 'action' => 'UPDATE', 'description' => 'Actualizar tipos de mantenimiento'],
            ['module' => 'MAINTENANCE_TYPES', 'action' => 'DELETE', 'description' => 'Eliminar tipos de mantenimiento'],

            // Gestión de Fabricantes y Proveedores
            ['module' => 'ASSET_VENDORS', 'action' => 'CREATE', 'description' => 'Crear fabricantes/proveedores'],
            ['module' => 'ASSET_VENDORS', 'action' => 'READ',   'description' => 'Consultar fabricantes/proveedores'],
            ['module' => 'ASSET_VENDORS', 'action' => 'UPDATE', 'description' => 'Actualizar fabricantes/proveedores'],
            ['module' => 'ASSET_VENDORS', 'action' => 'DELETE', 'description' => 'Eliminar fabricantes/proveedores'],

            // Órdenes de trabajo
            ['module' => 'WORK_ORDERS', 'action' => 'MANAGE_CHECKLIST', 'description' => 'Agregar ítems al checklist de órdenes de trabajo'],

            // Inspecciones Preoperacionales
            ['module' => 'INSPECTIONS', 'action' => 'CREATE',           'description' => 'Crear inspecciones'],
            ['module' => 'INSPECTIONS', 'action' => 'READ',             'description' => 'Consultar inspecciones'],
            ['module' => 'INSPECTIONS', 'action' => 'COMPLETE',         'description' => 'Completar inspecciones'],
            ['module' => 'INSPECTIONS', 'action' => 'DELETE',           'description' => 'Eliminar inspecciones'],
            ['module' => 'INSPECTIONS', 'action' => 'GENERATE_WR',      'description' => 'Generar solicitud de mantenimiento desde inspección'],
            ['module' => 'INSPECTION_TEMPLATES', 'action' => 'CREATE',  'description' => 'Crear plantillas de inspección'],
            ['module' => 'INSPECTION_TEMPLATES', 'action' => 'READ',    'description' => 'Consultar plantillas de inspección'],
            ['module' => 'INSPECTION_TEMPLATES', 'action' => 'UPDATE',  'description' => 'Actualizar plantillas de inspección'],
            ['module' => 'INSPECTION_TEMPLATES', 'action' => 'DELETE',  'description' => 'Eliminar plantillas de inspección'],
            ['module' => 'INSPECTION_SHIFTS', 'action' => 'CREATE',     'description' => 'Crear turnos de inspección'],
            ['module' => 'INSPECTION_SHIFTS', 'action' => 'READ',       'description' => 'Consultar turnos de inspección'],
            ['module' => 'INSPECTION_SHIFTS', 'action' => 'UPDATE',     'description' => 'Actualizar turnos de inspección'],
            ['module' => 'INSPECTION_SHIFTS', 'action' => 'DELETE',     'description' => 'Eliminar turnos de inspección'],
        ];

        foreach ($permissions as $index => $permission) {
            $code = $permission['module'] . '_' . $permission['action'];
            $name = $permission['description'];

            // Buscar el módulo por código para obtener su id
            $module = DB::table('modules')->where('code', $permission['module'])->first();

            DB::table('permissions')->updateOrInsert(
                ['code' => $code], // condition to check
                [
                    'code' => $code,
                    'name' => $name,
                    'action' => $permission['action'],
                    'description' => $permission['description'],
                    'module_id' => $module ? $module->id : null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Obtener los roles por código
        $roleSuperAdmin = DB::table('roles')->where('code', 'SUPER_ADMIN')->first();
        $roleAdmin = DB::table('roles')->where('code', 'ADMIN')->first();
        $roleSupervisor = DB::table('roles')->where('code', 'SUPERVISOR')->first();
        $roleWarehouseManager = DB::table('roles')->where('code', 'WAREHOUSE_MANAGER')->first();
        $roleEmployee = DB::table('roles')->where('code', 'EMPLOYEE')->first();
        $roleOperator = DB::table('roles')->where('code', 'OPERATOR')->first();
        $roleViewer = DB::table('roles')->where('code', 'VIEWER')->first();

        // Obtener todos los permisos para asignar por código
        $allPermissionIds = DB::table('permissions')->pluck('id')->toArray();
        
        // Función helper para obtener permission_id por código
        $getPermissionId = function($module, $action) {
            return DB::table('permissions')
                ->where('code', $module . '_' . $action)
                ->value('id');
        };

        // Asignación de permisos a roles
        $rolePermissions = [
            // Super Admin - todos los permisos
            ['role_id' => $roleSuperAdmin->id, 'permission_id' => $allPermissionIds],

            // Admin - permisos completos de empresa (solo permisos activos)
            ['role_id' => $roleAdmin->id, 'permission_id' => array_filter([
                $getPermissionId('USERS', 'CREATE'), $getPermissionId('USERS', 'READ'), $getPermissionId('USERS', 'UPDATE'), $getPermissionId('USERS', 'DELETE'), $getPermissionId('USERS', 'ASSIGN_ROLES'), $getPermissionId('USERS', 'MANAGE_SALARY'),
                $getPermissionId('ROLES', 'CREATE'), $getPermissionId('ROLES', 'READ'), $getPermissionId('ROLES', 'UPDATE'), $getPermissionId('ROLES', 'DELETE'), $getPermissionId('ROLES', 'ASSIGN_PERMISSIONS'),
                $getPermissionId('PERMISSIONS', 'CREATE'), $getPermissionId('PERMISSIONS', 'READ'), $getPermissionId('PERMISSIONS', 'UPDATE'), $getPermissionId('PERMISSIONS', 'DELETE'),
                $getPermissionId('DELEGATIONS', 'CREATE'), $getPermissionId('DELEGATIONS', 'READ'), $getPermissionId('DELEGATIONS', 'UPDATE'), $getPermissionId('DELEGATIONS', 'DELETE'), $getPermissionId('DELEGATIONS', 'REVOKE'),
                $getPermissionId('MODULES', 'CREATE'), $getPermissionId('MODULES', 'READ'), $getPermissionId('MODULES', 'UPDATE'), $getPermissionId('MODULES', 'DELETE'), $getPermissionId('MODULES', 'ENABLE'),
                $getPermissionId('COMPANIES', 'CREATE'), $getPermissionId('COMPANIES', 'READ'), $getPermissionId('COMPANIES', 'UPDATE'), $getPermissionId('COMPANIES', 'DELETE'),
                $getPermissionId('COMPANY_SITES', 'CREATE'), $getPermissionId('COMPANY_SITES', 'READ'), $getPermissionId('COMPANY_SITES', 'UPDATE'), $getPermissionId('COMPANY_SITES', 'DELETE'),
                $getPermissionId('ASSETS', 'CREATE'), $getPermissionId('ASSETS', 'READ'), $getPermissionId('ASSETS', 'UPDATE'), $getPermissionId('ASSETS', 'DELETE'),
                $getPermissionId('ASSETS', 'ASSIGN_USERS'), $getPermissionId('ASSETS', 'MANAGE_SPECS'), $getPermissionId('ASSETS', 'GENERATE_QR'),
                $getPermissionId('ASSETS', 'EXPORT'), $getPermissionId('ASSETS', 'IMPORT'), $getPermissionId('ASSETS', 'VIEW_STATS'),
                $getPermissionId('REPORTS', 'VIEW_BASIC'), $getPermissionId('REPORTS', 'VIEW_ADVANCED'), $getPermissionId('REPORTS', 'EXPORT'),
                $getPermissionId('SYSTEM', 'SETTINGS'), $getPermissionId('SYSTEM', 'AUDIT_LOGS'), $getPermissionId('SYSTEM', 'BACKUP'),
                // Módulos parametrizables
                $getPermissionId('JOB_POSITIONS', 'CREATE'), $getPermissionId('JOB_POSITIONS', 'READ'), $getPermissionId('JOB_POSITIONS', 'UPDATE'), $getPermissionId('JOB_POSITIONS', 'DELETE'),
                $getPermissionId('PRODUCTION_LINES', 'CREATE'), $getPermissionId('PRODUCTION_LINES', 'READ'), $getPermissionId('PRODUCTION_LINES', 'UPDATE'), $getPermissionId('PRODUCTION_LINES', 'DELETE'),
                $getPermissionId('ASSET_CATEGORIES', 'CREATE'), $getPermissionId('ASSET_CATEGORIES', 'READ'), $getPermissionId('ASSET_CATEGORIES', 'UPDATE'), $getPermissionId('ASSET_CATEGORIES', 'DELETE'),
                $getPermissionId('ASSET_SYSTEMS', 'CREATE'), $getPermissionId('ASSET_SYSTEMS', 'READ'), $getPermissionId('ASSET_SYSTEMS', 'UPDATE'), $getPermissionId('ASSET_SYSTEMS', 'DELETE'),
                $getPermissionId('MAINTENANCE_TYPES', 'CREATE'), $getPermissionId('MAINTENANCE_TYPES', 'READ'), $getPermissionId('MAINTENANCE_TYPES', 'UPDATE'), $getPermissionId('MAINTENANCE_TYPES', 'DELETE'),
                $getPermissionId('ASSET_VENDORS', 'CREATE'), $getPermissionId('ASSET_VENDORS', 'READ'), $getPermissionId('ASSET_VENDORS', 'UPDATE'), $getPermissionId('ASSET_VENDORS', 'DELETE'),
                // Inspecciones
                $getPermissionId('INSPECTIONS', 'CREATE'), $getPermissionId('INSPECTIONS', 'READ'),
                $getPermissionId('INSPECTIONS', 'COMPLETE'), $getPermissionId('INSPECTIONS', 'DELETE'),
                $getPermissionId('INSPECTIONS', 'GENERATE_WR'),
                $getPermissionId('INSPECTION_TEMPLATES', 'CREATE'), $getPermissionId('INSPECTION_TEMPLATES', 'READ'),
                $getPermissionId('INSPECTION_TEMPLATES', 'UPDATE'), $getPermissionId('INSPECTION_TEMPLATES', 'DELETE'),
                $getPermissionId('INSPECTION_SHIFTS', 'CREATE'), $getPermissionId('INSPECTION_SHIFTS', 'READ'),
                $getPermissionId('INSPECTION_SHIFTS', 'UPDATE'), $getPermissionId('INSPECTION_SHIFTS', 'DELETE'),
                // Órdenes de trabajo
                $getPermissionId('WORK_ORDERS', 'MANAGE_CHECKLIST'),
            ])],

            // Supervisor - Gestión operativa de activos
            ['role_id' => $roleSupervisor->id, 'permission_id' => array_filter([
                $getPermissionId('USERS', 'READ'),
                $getPermissionId('COMPANY_SITES', 'READ'),
                $getPermissionId('ASSETS', 'CREATE'), $getPermissionId('ASSETS', 'READ'), $getPermissionId('ASSETS', 'UPDATE'),
                $getPermissionId('ASSETS', 'ASSIGN_USERS'), $getPermissionId('ASSETS', 'MANAGE_SPECS'), $getPermissionId('ASSETS', 'GENERATE_QR'),
                $getPermissionId('ASSETS', 'EXPORT'), $getPermissionId('ASSETS', 'VIEW_STATS'),
                $getPermissionId('REPORTS', 'VIEW_BASIC'), $getPermissionId('REPORTS', 'EXPORT'),
                $getPermissionId('ASSET_VENDORS', 'READ'),
                $getPermissionId('WORK_ORDERS', 'MANAGE_CHECKLIST'),
            ])],

            // Warehouse Manager - Gestión completa de inventario
            ['role_id' => $roleWarehouseManager->id, 'permission_id' => array_filter([
                $getPermissionId('USERS', 'READ'),
                $getPermissionId('COMPANY_SITES', 'READ'),
                $getPermissionId('ASSETS', 'READ'),
                $getPermissionId('REPORTS', 'VIEW_BASIC')
            ])],

            // Employee - Consulta y actualización limitada
            ['role_id' => $roleEmployee->id, 'permission_id' => array_filter([
                $getPermissionId('COMPANY_SITES', 'READ'),
                $getPermissionId('ASSETS', 'READ'), $getPermissionId('ASSETS', 'UPDATE'),
                $getPermissionId('ASSETS', 'MANAGE_SPECS'), $getPermissionId('ASSETS', 'VIEW_STATS'),
                $getPermissionId('REPORTS', 'VIEW_BASIC'),
                $getPermissionId('ASSET_VENDORS', 'READ'),
                $getPermissionId('WORK_ORDERS', 'MANAGE_CHECKLIST'),
            ])],

            // Operator - Mismos permisos que Técnico (EMPLOYEE)
            ['role_id' => $roleOperator->id, 'permission_id' => array_filter([
                $getPermissionId('COMPANY_SITES', 'READ'),
                $getPermissionId('ASSETS', 'READ'), $getPermissionId('ASSETS', 'UPDATE'),
                $getPermissionId('ASSETS', 'MANAGE_SPECS'), $getPermissionId('ASSETS', 'VIEW_STATS'),
                $getPermissionId('REPORTS', 'VIEW_BASIC'),
                $getPermissionId('ASSET_VENDORS', 'READ'),
            ])],

            // Viewer - Solo consulta
            ['role_id' => $roleViewer->id, 'permission_id' => array_filter([
                $getPermissionId('USERS', 'READ'),
                $getPermissionId('COMPANY_SITES', 'READ'),
                $getPermissionId('ASSETS', 'READ'),
                $getPermissionId('REPORTS', 'VIEW_BASIC'),
                $getPermissionId('ASSET_VENDORS', 'READ'),
            ])],

            // Supervisor - puede gestionar plantillas y ver inspecciones
            ['role_id' => $roleSupervisor->id, 'permission_id' => array_filter([
                $getPermissionId('INSPECTIONS', 'CREATE'), $getPermissionId('INSPECTIONS', 'READ'),
                $getPermissionId('INSPECTIONS', 'COMPLETE'), $getPermissionId('INSPECTIONS', 'GENERATE_WR'),
                $getPermissionId('INSPECTION_TEMPLATES', 'CREATE'), $getPermissionId('INSPECTION_TEMPLATES', 'READ'),
                $getPermissionId('INSPECTION_TEMPLATES', 'UPDATE'),
                $getPermissionId('INSPECTION_SHIFTS', 'READ'),
            ])],

            // Operator - puede crear y completar inspecciones
            ['role_id' => $roleOperator->id, 'permission_id' => array_filter([
                $getPermissionId('INSPECTIONS', 'CREATE'), $getPermissionId('INSPECTIONS', 'READ'),
                $getPermissionId('INSPECTIONS', 'COMPLETE'), $getPermissionId('INSPECTIONS', 'GENERATE_WR'),
                $getPermissionId('INSPECTION_SHIFTS', 'READ'),
            ])],

            // Employee (Técnico) - puede ver inspecciones
            ['role_id' => $roleEmployee->id, 'permission_id' => array_filter([
                $getPermissionId('INSPECTIONS', 'READ'),
                $getPermissionId('INSPECTION_SHIFTS', 'READ'),
            ])],
        ];

        foreach ($rolePermissions as $assignment) {
            $roleId = $assignment['role_id'];
            $permissions = $assignment['permission_id'];
            
            // Si es un array de todos los permisos (Super Admin)
            if (!is_array($permissions)) {
                $permissions = [$permissions];
            }
            
            // Aplanar el array en caso de que haya arrays anidados
            $flatPermissions = [];
            foreach ($permissions as $perm) {
                if (is_array($perm)) {
                    // Si es array, agregar cada elemento
                    foreach ($perm as $p) {
                        if ($p !== null && $p !== false) {
                            $flatPermissions[] = $p;
                        }
                    }
                } else {
                    // Si no es array y no es null/false, agregarlo directamente
                    if ($perm !== null && $perm !== false) {
                        $flatPermissions[] = $perm;
                    }
                }
            }
            
            // Insertar cada permiso individualmente
            foreach ($flatPermissions as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId
                    ],
                    [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}