<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeographicDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insertar países (Colombia primero como país principal)
        $countries = [
            ['code' => 'COL', 'name' => 'Colombia'],
            ['code' => 'USA', 'name' => 'Estados Unidos'],
            ['code' => 'MEX', 'name' => 'México'],
            ['code' => 'ESP', 'name' => 'España'],
            ['code' => 'ARG', 'name' => 'Argentina'],
            ['code' => 'CHL', 'name' => 'Chile'],
            ['code' => 'PER', 'name' => 'Perú'],
            ['code' => 'BRA', 'name' => 'Brasil'],
        ];

        foreach ($countries as $country) {
            DB::table('countries')->insertOrIgnore([
                'code' => $country['code'],
                'name' => $country['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $colombiaId = DB::table('countries')->where('code', 'COL')->value('id');

        // Insertar departamentos de Colombia
        $departments = [
            ['code' => '05', 'name' => 'Antioquia', 'iso_code' => 'CO-ANT', 'dane_code' => '05', 'capital_city' => 'Medellín'],
            ['code' => '08', 'name' => 'Atlántico', 'iso_code' => 'CO-ATL', 'dane_code' => '08', 'capital_city' => 'Barranquilla'],
            ['code' => '11', 'name' => 'Bogotá D.C.', 'iso_code' => 'CO-DC', 'dane_code' => '11', 'capital_city' => 'Bogotá'],
            ['code' => '13', 'name' => 'Bolívar', 'iso_code' => 'CO-BOL', 'dane_code' => '13', 'capital_city' => 'Cartagena'],
            ['code' => '15', 'name' => 'Boyacá', 'iso_code' => 'CO-BOY', 'dane_code' => '15', 'capital_city' => 'Tunja'],
            ['code' => '17', 'name' => 'Caldas', 'iso_code' => 'CO-CAL', 'dane_code' => '17', 'capital_city' => 'Manizales'],
            ['code' => '18', 'name' => 'Caquetá', 'iso_code' => 'CO-CAQ', 'dane_code' => '18', 'capital_city' => 'Florencia'],
            ['code' => '19', 'name' => 'Cauca', 'iso_code' => 'CO-CAU', 'dane_code' => '19', 'capital_city' => 'Popayán'],
            ['code' => '20', 'name' => 'Cesar', 'iso_code' => 'CO-CES', 'dane_code' => '20', 'capital_city' => 'Valledupar'],
            ['code' => '23', 'name' => 'Córdoba', 'iso_code' => 'CO-COR', 'dane_code' => '23', 'capital_city' => 'Montería'],
            ['code' => '25', 'name' => 'Cundinamarca', 'iso_code' => 'CO-CUN', 'dane_code' => '25', 'capital_city' => 'Girardot'],
            ['code' => '27', 'name' => 'Chocó', 'iso_code' => 'CO-CHO', 'dane_code' => '27', 'capital_city' => 'Quibdó'],
            ['code' => '41', 'name' => 'Huila', 'iso_code' => 'CO-HUI', 'dane_code' => '41', 'capital_city' => 'Neiva'],
            ['code' => '44', 'name' => 'La Guajira', 'iso_code' => 'CO-LAG', 'dane_code' => '44', 'capital_city' => 'Riohacha'],
            ['code' => '47', 'name' => 'Magdalena', 'iso_code' => 'CO-MAG', 'dane_code' => '47', 'capital_city' => 'Santa Marta'],
            ['code' => '50', 'name' => 'Meta', 'iso_code' => 'CO-MET', 'dane_code' => '50', 'capital_city' => 'Villavicencio'],
            ['code' => '52', 'name' => 'Nariño', 'iso_code' => 'CO-NAR', 'dane_code' => '52', 'capital_city' => 'Pasto'],
            ['code' => '54', 'name' => 'Norte de Santander', 'iso_code' => 'CO-NSA', 'dane_code' => '54', 'capital_city' => 'Cúcuta'],
            ['code' => '63', 'name' => 'Quindío', 'iso_code' => 'CO-QUI', 'dane_code' => '63', 'capital_city' => 'Armenia'],
            ['code' => '66', 'name' => 'Risaralda', 'iso_code' => 'CO-RIS', 'dane_code' => '66', 'capital_city' => 'Pereira'],
            ['code' => '68', 'name' => 'Santander', 'iso_code' => 'CO-SAN', 'dane_code' => '68', 'capital_city' => 'Bucaramanga'],
            ['code' => '70', 'name' => 'Sucre', 'iso_code' => 'CO-SUC', 'dane_code' => '70', 'capital_city' => 'Sincelejo'],
            ['code' => '73', 'name' => 'Tolima', 'iso_code' => 'CO-TOL', 'dane_code' => '73', 'capital_city' => 'Ibagué'],
            ['code' => '76', 'name' => 'Valle del Cauca', 'iso_code' => 'CO-VAC', 'dane_code' => '76', 'capital_city' => 'Cali'],
            ['code' => '81', 'name' => 'Arauca', 'iso_code' => 'CO-ARA', 'dane_code' => '81', 'capital_city' => 'Arauca'],
            ['code' => '85', 'name' => 'Casanare', 'iso_code' => 'CO-CAS', 'dane_code' => '85', 'capital_city' => 'Yopal'],
            ['code' => '86', 'name' => 'Putumayo', 'iso_code' => 'CO-PUT', 'dane_code' => '86', 'capital_city' => 'Mocoa'],
            ['code' => '88', 'name' => 'Archipiélago de San Andrés', 'iso_code' => 'CO-SAP', 'dane_code' => '88', 'capital_city' => 'San Andrés'],
            ['code' => '91', 'name' => 'Amazonas', 'iso_code' => 'CO-AMA', 'dane_code' => '91', 'capital_city' => 'Leticia'],
            ['code' => '94', 'name' => 'Guainía', 'iso_code' => 'CO-GUA', 'dane_code' => '94', 'capital_city' => 'Inírida'],
            ['code' => '95', 'name' => 'Guaviare', 'iso_code' => 'CO-GUV', 'dane_code' => '95', 'capital_city' => 'San José del Guaviare'],
            ['code' => '97', 'name' => 'Vaupés', 'iso_code' => 'CO-VAU', 'dane_code' => '97', 'capital_city' => 'Mitú'],
            ['code' => '99', 'name' => 'Vichada', 'iso_code' => 'CO-VID', 'dane_code' => '99', 'capital_city' => 'Puerto Carreño'],
        ];

        foreach ($departments as $dept) {
            DB::table('departments_geo')->insertOrIgnore([
                'country_id' => $colombiaId,
                'code' => $dept['code'],
                'name' => $dept['name'],
                'iso_code' => $dept['iso_code'],
                'dane_code' => $dept['dane_code'],
                'capital_city' => $dept['capital_city'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Obtener todos los IDs de departamentos
        $deptIds = DB::table('departments_geo')
            ->pluck('id', 'dane_code');

        // Leer municipios desde archivo JSON
        $jsonPath = database_path('seeders/data/colombia_municipalities.json');
        
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            $data = json_decode($jsonContent, true);
            $municipalities = $data['municipalities'] ?? [];
            
            $this->command->info('📍 Insertando ' . count($municipalities) . ' municipios desde JSON...');
            
            $inserted = 0;
            foreach ($municipalities as $municipality) {
                if (isset($deptIds[$municipality['dept_code']])) {
                    DB::table('municipalities')->insertOrIgnore([
                        'department_geo_id' => $deptIds[$municipality['dept_code']],
                        'dane_code' => $municipality['dane_code'],
                        'name' => $municipality['name'],
                        'municipality_type' => $municipality['type'],
                        'population_category' => $municipality['category'],
                        'is_capital' => $municipality['capital'],
                        'altitude_meters' => $municipality['altitude'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $inserted++;
                }
            }
            
            $this->command->info("✅ {$inserted} municipios insertados correctamente");
        } else {
            $this->command->warn('⚠️  Archivo JSON de municipios no encontrado. Usando datos básicos...');
            
            // Fallback: Insertar solo capitales departamentales
            $basicMunicipalities = [
                ['dept_code' => '11', 'dane_code' => '11001', 'name' => 'Bogotá', 'type' => 'distrito', 'category' => 'especial', 'capital' => true, 'altitude' => 2640],
                ['dept_code' => '05', 'dane_code' => '05001', 'name' => 'Medellín', 'type' => 'municipio', 'category' => 'especial', 'capital' => true, 'altitude' => 1495],
                ['dept_code' => '76', 'dane_code' => '76001', 'name' => 'Cali', 'type' => 'municipio', 'category' => 'especial', 'capital' => true, 'altitude' => 1018],
                ['dept_code' => '08', 'dane_code' => '08001', 'name' => 'Barranquilla', 'type' => 'distrito', 'category' => 'especial', 'capital' => true, 'altitude' => 18],
                ['dept_code' => '68', 'dane_code' => '68001', 'name' => 'Bucaramanga', 'type' => 'municipio', 'category' => 'especial', 'capital' => true, 'altitude' => 959],
                ['dept_code' => '13', 'dane_code' => '13001', 'name' => 'Cartagena', 'type' => 'distrito', 'category' => 'especial', 'capital' => true, 'altitude' => 2],
                ['dept_code' => '66', 'dane_code' => '66001', 'name' => 'Pereira', 'type' => 'municipio', 'category' => 'primera', 'capital' => true, 'altitude' => 1411],
                ['dept_code' => '63', 'dane_code' => '63001', 'name' => 'Armenia', 'type' => 'municipio', 'category' => 'primera', 'capital' => true, 'altitude' => 1483],
                ['dept_code' => '17', 'dane_code' => '17001', 'name' => 'Manizales', 'type' => 'municipio', 'category' => 'primera', 'capital' => true, 'altitude' => 2153],
                ['dept_code' => '54', 'dane_code' => '54001', 'name' => 'Cúcuta', 'type' => 'municipio', 'category' => 'primera', 'capital' => true, 'altitude' => 320],
                ['dept_code' => '73', 'dane_code' => '73001', 'name' => 'Ibagué', 'type' => 'municipio', 'category' => 'primera', 'capital' => true, 'altitude' => 1285],
                ['dept_code' => '52', 'dane_code' => '52001', 'name' => 'Pasto', 'type' => 'municipio', 'category' => 'primera', 'capital' => true, 'altitude' => 2527],
                ['dept_code' => '50', 'dane_code' => '50001', 'name' => 'Villavicencio', 'type' => 'municipio', 'category' => 'primera', 'capital' => true, 'altitude' => 467],
                ['dept_code' => '47', 'dane_code' => '47001', 'name' => 'Santa Marta', 'type' => 'distrito', 'category' => 'especial', 'capital' => true, 'altitude' => 6],
                ['dept_code' => '20', 'dane_code' => '20001', 'name' => 'Valledupar', 'type' => 'municipio', 'category' => 'primera', 'capital' => true, 'altitude' => 200],
            ];
            
            foreach ($basicMunicipalities as $municipality) {
                if (isset($deptIds[$municipality['dept_code']])) {
                    DB::table('municipalities')->insertOrIgnore([
                        'department_geo_id' => $deptIds[$municipality['dept_code']],
                        'dane_code' => $municipality['dane_code'],
                        'name' => $municipality['name'],
                        'municipality_type' => $municipality['type'],
                        'population_category' => $municipality['category'],
                        'is_capital' => $municipality['capital'],
                        'altitude_meters' => $municipality['altitude'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}