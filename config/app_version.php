<?php

/**
 * Valores de fallback para el control de versiones de la app móvil.
 *
 * La fuente primaria es SystemSetting (BD con cache 1h).
 * Este archivo solo se usa si la clave no existe en la tabla system_settings.
 *
 * Para gestionar versiones SIN reiniciar el servidor, usa directamente:
 *   SystemSetting::set('mobile_version_enforce',      true,    '...', 'boolean');
 *   SystemSetting::set('mobile_version_minimum',      '1.1.0', '...', 'string');
 *   SystemSetting::set('mobile_version_latest',       '1.1.0', '...', 'string');
 *   SystemSetting::set('mobile_version_download_url', 'https://...', '...', 'string');
 *
 * O ejecutando el seeder después de migrar:
 *   php artisan db:seed --class=SystemDataSeeder
 */
return [
    'enforce'         => false,
    'minimum_version' => '1.0.0',
    'latest_version'  => '1.0.0',
    'download_url'    => '',
    'update_message'  => 'Hay una nueva versión disponible. Por favor actualice la aplicación.',
    'release_notes'   => '',
];
