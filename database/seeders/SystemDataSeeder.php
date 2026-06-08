<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Monedas
        $currencies = [
            [
                'code' => 'COP',
                'name' => 'Peso Colombiano',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'USD',
                'name' => 'Dólar Estadounidense',
                'symbol' => 'US$',
                'decimal_places' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
                'is_active' => true,
            ]
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']], // condition to check
                [
                    ...$currency,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Configuraciones del sistema
        $systemSettings = [
            [
                'key' => 'app_name',
                'value' => 'RECYLO System',
                'description' => 'Nombre de la aplicación',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'app_version',
                'value' => '1.0.0',
                'description' => 'Versión de la aplicación',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'default_currency',
                'value' => 'COP',
                'description' => 'Moneda por defecto del sistema',
                'type' => 'string',
                'is_public' => false,
            ],
            [
                'key' => 'tax_rate',
                'value' => '19',
                'description' => 'Tasa de IVA por defecto (%)',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'max_file_upload_size',
                'value' => '10485760',
                'description' => 'Tamaño máximo de archivo en bytes (10MB)',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'allowed_file_types',
                'value' => '["pdf","doc","docx","xls","xlsx","jpg","jpeg","png","gif"]',
                'description' => 'Tipos de archivo permitidos',
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'key' => 'session_lifetime',
                'value' => '480',
                'description' => 'Tiempo de vida de sesión en minutos',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'backup_retention_days',
                'value' => '30',
                'description' => 'Días de retención de respaldos',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
                'description' => 'Modo de mantenimiento activo',
                'type' => 'boolean',
                'is_public' => true,
            ],

            // ── App móvil — control de versiones ────────────────────────────
            [
                'key' => 'mobile_version_enforce',
                'value' => 'false',
                'description' => 'Si true, bloquea la app móvil cuando la versión instalada es menor a la mínima requerida',
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'key' => 'mobile_version_minimum',
                'value' => '1.0.0',
                'description' => 'Versión mínima requerida de la app móvil (semver: major.minor.patch)',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'mobile_version_latest',
                'value' => '1.0.0',
                'description' => 'Última versión disponible del APK para descarga',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'mobile_version_download_url',
                'value' => '',
                'description' => 'URL directa al APK (Google Drive: https://drive.google.com/uc?export=download&id=FILE_ID)',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'mobile_version_update_message',
                'value' => 'Hay una nueva versión disponible con mejoras importantes. Por favor actualice la aplicación.',
                'description' => 'Mensaje personalizado que ve el usuario en la pantalla de actualización',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'mobile_version_release_notes',
                'value' => '',
                'description' => 'Notas de la última versión (se muestra en la pantalla de actualización)',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'key' => 'mobile_distribution_channel',
                'value' => 'drive',
                'description' => 'Canal de distribución de la app móvil: drive | playstore',
                'type' => 'string',
                'is_public' => true,
            ],
        ];

        foreach ($systemSettings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']], // condition to check
                [
                    ...$setting,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Acciones de auditoría
        $auditActions = [
            // Acciones genéricas (usadas por Auditable trait)
            ['name' => 'created', 'description' => 'Registro creado', 'module' => 'SYSTEM', 'severity' => 'MEDIUM'],
            ['name' => 'updated', 'description' => 'Registro actualizado', 'module' => 'SYSTEM', 'severity' => 'MEDIUM'],
            ['name' => 'deleted', 'description' => 'Registro eliminado', 'module' => 'SYSTEM', 'severity' => 'HIGH'],
            ['name' => 'restored', 'description' => 'Registro restaurado', 'module' => 'SYSTEM', 'severity' => 'MEDIUM'],

            // Usuarios
            ['name' => 'USER_CREATED', 'description' => 'Usuario creado', 'module' => 'USER', 'severity' => 'MEDIUM'],
            ['name' => 'USER_UPDATED', 'description' => 'Usuario actualizado', 'module' => 'USER', 'severity' => 'MEDIUM'],
            ['name' => 'USER_DELETED', 'description' => 'Usuario eliminado', 'module' => 'USER', 'severity' => 'HIGH'],
            ['name' => 'USER_LOGIN', 'description' => 'Inicio de sesión', 'module' => 'USER', 'severity' => 'LOW'],
            ['name' => 'USER_LOGOUT', 'description' => 'Cierre de sesión', 'module' => 'USER', 'severity' => 'LOW'],
            ['name' => 'USER_PASSWORD_CHANGED', 'description' => 'Cambio de contraseña', 'module' => 'USER', 'severity' => 'MEDIUM'],

            // Empresas
            ['name' => 'COMPANY_CREATED', 'description' => 'Empresa creada', 'module' => 'COMPANY', 'severity' => 'HIGH'],
            ['name' => 'COMPANY_UPDATED', 'description' => 'Empresa actualizada', 'module' => 'COMPANY', 'severity' => 'MEDIUM'],
            ['name' => 'COMPANY_DELETED', 'description' => 'Empresa eliminada', 'module' => 'COMPANY', 'severity' => 'CRITICAL'],
            // Sistema
            ['name' => 'SYSTEM_SETTING_CHANGED', 'description' => 'Configuración del sistema modificada', 'module' => 'SYSTEM', 'severity' => 'HIGH'],
            ['name' => 'BACKUP_CREATED', 'description' => 'Respaldo creado', 'module' => 'SYSTEM', 'severity' => 'LOW'],
            ['name' => 'DATA_EXPORT', 'description' => 'Exportación de datos', 'module' => 'SYSTEM', 'severity' => 'MEDIUM'],
        ];

        foreach ($auditActions as $action) {
            DB::table('audit_actions')->updateOrInsert(
                ['name' => $action['name']], // condition to check
                [
                    'log_details' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                    ...$action
                ]
            );
        }
    }
}