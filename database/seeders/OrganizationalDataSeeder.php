<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationalDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Site Types
        $siteTypes = [
            ['code' => 'HEAD_OFFICE', 'name' => 'Sede Principal', 'description' => 'Oficina principal o casa matriz de la empresa'],
            ['code' => 'BRANCH', 'name' => 'Sucursal', 'description' => 'Sucursal u oficina secundaria'],
            ['code' => 'PLANT', 'name' => 'Planta Industrial', 'description' => 'Planta de producción o manufactura'],
            ['code' => 'WAREHOUSE', 'name' => 'Bodega/Almacén', 'description' => 'Centro de almacenamiento y distribución'],
            ['code' => 'STORE', 'name' => 'Tienda/Local', 'description' => 'Punto de venta al público'],
            ['code' => 'REMOTE', 'name' => 'Ubicación Remota', 'description' => 'Trabajo remoto o teletrabajo'],
        ];

        foreach ($siteTypes as $type) {
            DB::table('site_types')->insertOrIgnore([
                'code' => $type['code'],
                'name' => $type['name'],
                'description' => $type['description'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

    }
}