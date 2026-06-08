<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed del catálogo de tipos de mantenimiento — HU-A4.
 *
 * Asigna los 9 tipos al tenant de prueba (company_id = 1).
 * En producción, cada empresa administra su propio catálogo desde la UI.
 */
class MaintenanceTypesSeeder extends Seeder
{
    public function run(): void
    {
        // Solo sembrar si existe la empresa con id=1
        $companyExists = DB::table('companies')->where('id', 1)->exists();
        if (!$companyExists) {
            $this->command->warn('⚠️  No se encontró company_id=1. Se omite el seeder de tipos de mantenimiento.');
            return;
        }

        $types = [
            ['code' => 'MECH',       'name' => 'Mecánico',                 'description' => 'Mantenimiento de componentes mecánicos: rodamientos, sellos, transmisiones, etc.'],
            ['code' => 'ELEC',       'name' => 'Eléctrico',                'description' => 'Mantenimiento de sistemas eléctricos: motores, tableros, cables, etc.'],
            ['code' => 'INSTR',      'name' => 'Instrumentación',          'description' => 'Mantenimiento de sensores, transmisores, válvulas de control y sistemas de medición.'],
            ['code' => 'CIVIL',      'name' => 'Civil',                    'description' => 'Mantenimiento de obras civiles, estructuras, pisos y edificaciones.'],
            ['code' => 'ELECTROMEC', 'name' => 'Electromecánico',          'description' => 'Mantenimiento de equipos que combinan componentes eléctricos y mecánicos.'],
            ['code' => 'LUBRIC',     'name' => 'Lubricación',              'description' => 'Actividades de lubricación de rodamientos, cadenas, engranajes y guías.'],
            ['code' => 'HYDRAULIC',  'name' => 'Hidráulico / Neumático',   'description' => 'Mantenimiento de sistemas hidráulicos y neumáticos.'],
            ['code' => 'AUTO',       'name' => 'Automatización / Control', 'description' => 'Mantenimiento de PLCs, HMIs, variadores de velocidad y sistemas SCADA.'],
            ['code' => 'GENERAL',    'name' => 'Servicios generales',      'description' => 'Mantenimiento general de instalaciones, limpieza técnica y servicios varios.'],
        ];

        foreach ($types as $type) {
            DB::table('maintenance_types')->insertOrIgnore([
                'company_id'  => 1,
                'code'        => $type['code'],
                'name'        => $type['name'],
                'description' => $type['description'],
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $this->command->info('✅ 9 tipos de mantenimiento creados para company_id=1 (HU-A4)');
    }
}
