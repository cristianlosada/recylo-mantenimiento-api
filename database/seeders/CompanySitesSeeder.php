<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySitesSeeder extends Seeder
{
    /**
     * Seed company sites (sedes de empresa)
     * Requiere: companies, site_types, municipalities
     */
    public function run(): void
    {
        // Obtener IDs necesarios
        $company = DB::table('companies')->where('tax_id', '901234567-1')->first();
        
        if (!$company) {
            $this->command->warn('⚠️  No se encontró empresa de prueba. Ejecute TestDataSeeder primero.');
            return;
        }

        // Obtener tipos de sede
        $headOfficeType = DB::table('site_types')->where('code', 'HEAD_OFFICE')->first();

        // Obtener municipios (ajustar IDs según datos reales)
        $neiva = DB::table('municipalities')->where('name', 'like', '%Neiva%')->first();

        // Sedes de prueba
        $sites = [
            [
                'company_id' => $company->id,
                'site_type_id' => $headOfficeType?->id ?? 1,
                'name' => 'Planta Central Neiva',
                'municipality_id' => $neiva?->id ?? 1,
                'address_line_1' => 'Calle 100 # 19-45',
                'address_line_2' => 'Torre B, Piso 12',
                'postal_code' => '110111',
                'latitude' => 4.6892,
                'longitude' => -74.0464,
                'is_headquarters' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Insertar sedes
        foreach ($sites as $site) {
            DB::table('company_sites')->insertOrIgnore($site);
        }

        $this->command->info('✅ Sedes de empresa creadas: ' . count($sites));
    }
}
