<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModulesAndPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Módulos del sistema
        $modules = [
            [
                'code' => 'COMPANIES',
                'name' => 'Gestión de Empresas',
                'description' => 'Módulo para gestión de empresas y organizaciones',
                'icon' => 'building',
                'order' => 100,
                'is_active' => true,
                'is_core' => true,
            ],
            [
                'code' => 'COMPANY_SITES',
                'name' => 'Gestión de Sedes de Empresa',
                'description' => 'Módulo para gestión de sedes de empresas',
                'icon' => 'map-pin',
                'order' => 101,
                'is_active' => true,
                'is_core' => true,
            ],
            [
                'code' => 'ASSETS',
                'name' => 'Gestión de Activos',
                'description' => 'Módulo CMMS para gestión de activos, equipos e inventario',
                'icon' => 'box',
                'order' => 200,
                'is_active' => true,
                'is_core' => false,
            ],
            [
                'code' => 'USERS',
                'name' => 'Usuarios',
                'description' => 'Módulo para gestión de usuarios del sistema',
                'icon' => 'users',
                'order' => 102,
                'is_active' => true,
                'is_core' => true,
            ],
            [
                'code' => 'ROLES',
                'name' => 'Gestión de Roles y Permisos',
                'description' => 'Módulo para gestión de roles y permisos del sistema',
                'icon' => 'user-shield',
                'order' => 108,
                'is_active' => true,
                'is_core' => true,
            ],
            [
                'code' => 'PERMISSIONS',
                'name' => 'Gestión de Permisos',
                'description' => 'Módulo para gestión de permisos del sistema',
                'icon' => 'key',
                'order' => 108,
                'is_active' => true,
                'is_core' => true,
            ],
            [
                'code' => 'DELEGATIONS',
                'name' => 'Delegaciones de Permisos',
                'description' => 'Módulo para delegar permisos a otros usuarios',
                'icon' => 'user-check',
                'order' => 108,
                'is_active' => true,
                'is_core' => true,
            ],
            [
                'code' => 'MODULES',
                'name' => 'Gestión de Módulos',
                'description' => 'Módulo para administrar módulos del sistema',
                'icon' => 'th-large',
                'order' => 109,
                'is_active' => true,
                'is_core' => true,
            ],
            [
                'code' => 'SYSTEM',
                'name' => 'Sistema',
                'description' => 'Módulo de configuración del sistema',
                'icon' => 'cog',
                'order' => 110,
                'is_active' => true,
                'is_core' => true,
            ],

            // Módulos parametrizables de CMMS
            [
                'code'        => 'JOB_POSITIONS',
                'name'        => 'Cargos',
                'description' => 'Catálogo de cargos laborales de la empresa',
                'icon'        => 'briefcase',
                'order'       => 103,
                'is_active'   => true,
                'is_core'     => true,
            ],
            [
                'code' => 'PRODUCTION_LINES',
                'name' => 'Áreas',
                'description' => 'Gestión de áreas de producción de la empresa',
                'icon' => 'industry',
                'order' => 201,
                'is_active' => true,
                'is_core' => false,
            ],
            [
                'code' => 'ASSET_CATEGORIES',
                'name' => 'Categorías de Activos',
                'description' => 'Gestión del catálogo de categorías de activos',
                'icon' => 'tags',
                'order' => 202,
                'is_active' => true,
                'is_core' => false,
            ],
            [
                'code' => 'ASSET_SYSTEMS',
                'name' => 'Sistemas de Activos',
                'description' => 'Gestión de sistemas funcionales de los activos',
                'icon' => 'cogs',
                'order' => 203,
                'is_active' => true,
                'is_core' => false,
            ],
            [
                'code' => 'MAINTENANCE_TYPES',
                'name' => 'Tipos de Mantenimiento',
                'description' => 'Gestión del catálogo de tipos de mantenimiento',
                'icon' => 'wrench',
                'order' => 204,
                'is_active' => true,
                'is_core' => false,
            ],
            [
                'code'        => 'ASSET_VENDORS',
                'name'        => 'Fabricantes y Proveedores',
                'description' => 'Gestión del catálogo de fabricantes y proveedores de activos',
                'icon'        => 'truck',
                'order'       => 205,
                'is_active'   => true,
                'is_core'     => false,
            ],
            [
                'code'        => 'INSPECTIONS',
                'name'        => 'Inspecciones Preoperacionales',
                'description' => 'Módulo de inspecciones preoperacionales de maquinaria',
                'icon'        => 'clipboard-check',
                'order'       => 300,
                'is_active'   => true,
                'is_core'     => false,
            ],
            [
                'code'        => 'INSPECTION_TEMPLATES',
                'name'        => 'Plantillas de Inspección',
                'description' => 'Gestión de plantillas y checklists de inspección',
                'icon'        => 'file-alt',
                'order'       => 301,
                'is_active'   => true,
                'is_core'     => false,
            ],
            [
                'code'        => 'INSPECTION_SHIFTS',
                'name'        => 'Turnos de Inspección',
                'description' => 'Gestión de turnos para inspecciones',
                'icon'        => 'clock',
                'order'       => 302,
                'is_active'   => true,
                'is_core'     => false,
            ],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['code' => $module['code']], // condition to check
                [
                    ...$module,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Planes comerciales
        $plans = [
            [
                'code' => 'BASIC',
                'name' => 'Plan Básico',
                'description' => 'Plan básico para microempresas (1-10 empleados)',
                'price' => 150000.00,
                'currency' => 'COP',
                'billing_cycle_days' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'STANDARD',
                'name' => 'Plan Estándar',
                'description' => 'Plan estándar para pequeñas empresas (11-50 empleados)',
                'price' => 300000.00,
                'currency' => 'COP',
                'billing_cycle_days' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'PROFESSIONAL',
                'name' => 'Plan Profesional',
                'description' => 'Plan profesional para medianas empresas (51-200 empleados)',
                'price' => 600000.00,
                'currency' => 'COP',
                'billing_cycle_days' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'ENTERPRISE',
                'name' => 'Plan Empresarial',
                'description' => 'Plan empresarial para grandes empresas (200+ empleados)',
                'price' => 1200000.00,
                'currency' => 'COP',
                'billing_cycle_days' => 30,
                'is_active' => true,
            ]
        ];

        foreach ($plans as $plan) {
            DB::table('plans')->updateOrInsert(
                ['code' => $plan['code']], // condition to check
                [
                    ...$plan,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Obtener los IDs de planes y módulos por código
        $planBasic = DB::table('plans')->where('code', 'BASIC')->first();
        $planStandard = DB::table('plans')->where('code', 'STANDARD')->first();
        $planProfessional = DB::table('plans')->where('code', 'PROFESSIONAL')->first();
        $planEnterprise = DB::table('plans')->where('code', 'ENTERPRISE')->first();
        // Relación Plan-Módulos
        $planModules = [];

        foreach ($planModules as $planModule) {
            DB::table('plan_modules')->updateOrInsert(
                [
                    'plan_id' => $planModule['plan_id'],
                    'module_id' => $planModule['module_id']
                ],
                [
                    ...$planModule,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Estados de suscripción
        $subscriptionStatuses = [
            [
                'code' => 'ACTIVE',
                'name' => 'Activa',
                'description' => 'Suscripción activa y vigente',
                'is_active' => true,
            ],
            [
                'code' => 'PENDING',
                'name' => 'Pendiente',
                'description' => 'Suscripción pendiente de activación',
                'is_active' => true,
            ],
            [
                'code' => 'SUSPENDED',
                'name' => 'Suspendida',
                'description' => 'Suscripción suspendida por falta de pago',
                'is_active' => true,
            ],
            [
                'code' => 'CANCELLED',
                'name' => 'Cancelada',
                'description' => 'Suscripción cancelada por el usuario',
                'is_active' => true,
            ],
            [
                'code' => 'EXPIRED',
                'name' => 'Expirada',
                'description' => 'Suscripción expirada',
                'is_active' => true,
            ]
        ];

        foreach ($subscriptionStatuses as $status) {
            DB::table('subscription_statuses')->updateOrInsert(
                ['code' => $status['code']], // condition to check
                [
                    ...$status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}