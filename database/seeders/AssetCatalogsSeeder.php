<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetCatalogsSeeder extends Seeder
{
    /**
     * Seed asset catalog tables (categories, statuses, priorities).
     */
    public function run(): void
    {
        $this->seedCategories();
        $this->seedStatuses();
        $this->seedPriorities();
        $this->seedProductionLines();
        $this->seedAssetSystems();
    }

    /**
     * Seed asset_categories table.
     */
    private function seedCategories(): void
    {
        // HU-A2: categorías ajustadas para industria química/manufactura
        $categories = [
            [
                'code' => 'PROCESS_MACHINERY',
                'name' => 'Maquinaria de proceso',
                'description' => 'Equipos directamente involucrados en los procesos de producción (bombas, reactores, compresores, etc.)',
                'icon' => '⚙️',
                'color' => '#8B5CF6',
                'is_active' => true,
            ],
            [
                'code' => 'AUX_EQUIPMENT',
                'name' => 'Equipos auxiliares',
                'description' => 'Equipos de soporte a la producción (aires acondicionados, UPS, generadores, etc.)',
                'icon' => '🔩',
                'color' => '#3B82F6',
                'is_active' => true,
            ],
            [
                'code' => 'TRANSPORT_SYSTEMS',
                'name' => 'Sistemas de transporte',
                'description' => 'Bandas transportadoras, elevadores, tornillos sinfín, sistemas de tuberías',
                'icon' => '🔄',
                'color' => '#F59E0B',
                'is_active' => true,
            ],
            [
                'code' => 'ELECTRICAL_SYSTEMS',
                'name' => 'Sistemas eléctricos',
                'description' => 'Tableros eléctricos, transformadores, subestaciones, instalaciones eléctricas',
                'icon' => '⚡',
                'color' => '#EAB308',
                'is_active' => true,
            ],
            [
                'code' => 'INSTRUMENTATION',
                'name' => 'Instrumentación y control',
                'description' => 'Sensores, transmisores, válvulas de control, PLCs, sistemas SCADA',
                'icon' => '📡',
                'color' => '#06B6D4',
                'is_active' => true,
            ],
            [
                'code' => 'INFRASTRUCTURE',
                'name' => 'Infraestructura',
                'description' => 'Edificios, estructuras civiles, tanques, tuberías fijas, obras civiles',
                'icon' => '🏭',
                'color' => '#6B7280',
                'is_active' => true,
            ],
            [
                'code' => 'HEAVY_MACHINERY',
                'name' => 'Maquinaria amarilla / maquinaria pesada',
                'description' => 'Excavadoras, retroexcavadoras, cargadores, grúas y equipo pesado',
                'icon' => '🚜',
                'color' => '#D97706',
                'is_active' => true,
            ],
            [
                'code' => 'VEHICLE',
                'name' => 'Vehículos',
                'description' => 'Vehículos livianos, camiones, montacargas, equipos de transporte interno',
                'icon' => '🚗',
                'color' => '#EF4444',
                'is_active' => true,
            ],
            [
                'code' => 'TOOLS',
                'name' => 'Herramientas',
                'description' => 'Herramientas manuales, eléctricas, neumáticas y equipos de medición',
                'icon' => '🔧',
                'color' => '#10B981',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('asset_categories')->insertOrIgnore($category);
        }

        $this->command->info('✅ 9 categorías de activos creadas (HU-A2)');
    }

    /**
     * Seed asset_statuses table.
     */
    private function seedStatuses(): void
    {
        $statuses = [
            [
                'code' => 'ACTIVE',
                'name' => 'Activo',
                'description' => 'El activo está en operación normal',
                'color' => 'success',
                'requires_note' => false,
                'is_operational' => true,
                'is_active' => true,
            ],
            [
                'code' => 'MAINTENANCE',
                'name' => 'En Mantenimiento',
                'description' => 'El activo está en mantenimiento preventivo o correctivo',
                'color' => 'warning',
                'requires_note' => true,
                'is_operational' => true,
                'is_active' => true,
            ],
            [
                'code' => 'OUT_OF_SERVICE',
                'name' => 'Fuera de Servicio',
                'description' => 'El activo no está operativo por falla o daño',
                'color' => 'danger',
                'requires_note' => true,
                'is_operational' => false,
                'is_active' => true,
            ],
            [
                'code' => 'STANDBY',
                'name' => 'En Espera',
                'description' => 'El activo está disponible pero no en uso actualmente',
                'color' => 'info',
                'requires_note' => false,
                'is_operational' => true,
                'is_active' => true,
            ],
            [
                'code' => 'RETIRED',
                'name' => 'Retirado',
                'description' => 'El activo ha sido dado de baja o retirado del servicio',
                'color' => 'secondary',
                'requires_note' => true,
                'is_operational' => false,
                'is_active' => true,
            ],
        ];

        foreach ($statuses as $status) {
            DB::table('asset_statuses')->insertOrIgnore($status);
        }

        $this->command->info('✅ 5 estados de activos creados');
    }

    /**
     * Seed asset_priorities table.
     */
    private function seedPriorities(): void
    {
        $priorities = [
            [
                'code' => 'LOW',
                'name' => 'Baja',
                'level' => 1,
                'color' => 'success',
                'description' => 'Prioridad baja - No crítico para la operación',
                'is_active' => true,
            ],
            [
                'code' => 'MEDIUM',
                'name' => 'Media',
                'level' => 2,
                'color' => 'info',
                'description' => 'Prioridad media - Importante pero no urgente',
                'is_active' => true,
            ],
            [
                'code' => 'HIGH',
                'name' => 'Alta',
                'level' => 3,
                'color' => 'warning',
                'description' => 'Prioridad alta - Importante para la operación',
                'is_active' => true,
            ],
            [
                'code' => 'CRITICAL',
                'name' => 'Crítica',
                'level' => 4,
                'color' => 'danger',
                'description' => 'Prioridad crítica - Esencial para la operación',
                'is_active' => true,
            ],
        ];

        foreach ($priorities as $priority) {
            DB::table('asset_priorities')->insertOrIgnore($priority);
        }

        $this->command->info('✅ 4 prioridades de activos creadas');
    }

    /**
     * Seed production_lines table for company_id = 1 (HU-A1).
     */
    private function seedProductionLines(): void
    {
        $companyExists = DB::table('companies')->where('id', 1)->exists();
        if (!$companyExists) {
            $this->command->warn('⚠️  No se encontró company_id=1. Se omite el seeder de líneas de producción.');
            return;
        }

        $lines = [
            ['code' => 'ADMIN',          'name' => 'Administración',            'description' => 'Área administrativa y de gestión general'],
            ['code' => 'HORNO_I',        'name' => 'Horno I',                   'description' => 'Línea de proceso — Horno I'],
            ['code' => 'HORNO_II',       'name' => 'Horno II',                  'description' => 'Línea de proceso — Horno II'],
            ['code' => 'DEN_ACIDSULF',   'name' => 'DEN - Acidulación Sulfúrico','description' => 'Línea de densificación y acidulación con ácido sulfúrico'],
            ['code' => 'FOSFORICO',      'name' => 'Fosfórico',                 'description' => 'Línea de producción de ácido fosfórico'],
            ['code' => 'MANTENIMIENTO',  'name' => 'Mantenimiento',             'description' => 'Área de mantenimiento general de planta'],
            ['code' => 'MAQ_AMARILLA',   'name' => 'Maquinaria Amarilla',       'description' => 'Flota de maquinaria pesada y equipo amarillo'],
            ['code' => 'MEZCLAS_AUTO',   'name' => 'Mezclas Automáticas',       'description' => 'Línea de mezclas automáticas de fertilizantes'],
            ['code' => 'RAYMOND_1',      'name' => 'Raymond 1',                 'description' => 'Molino Raymond — unidad 1'],
            ['code' => 'RAYMOND_2',      'name' => 'Raymond 2',                 'description' => 'Molino Raymond — unidad 2'],
            ['code' => 'RAYMOND_3',      'name' => 'Raymond 3',                 'description' => 'Molino Raymond — unidad 3'],
            ['code' => 'RAYMOND_4',      'name' => 'Raymond 4',                 'description' => 'Molino Raymond — unidad 4'],
            ['code' => 'SERV_MEZCLAS',   'name' => 'Servicio de Mezclas',       'description' => 'Área de servicio y despacho de mezclas personalizadas'],
            ['code' => 'SULF_1',         'name' => 'Sulfúrico 1',               'description' => 'Línea de producción de ácido sulfúrico — tren 1'],
            ['code' => 'SULF_2',         'name' => 'Sulfúrico 2',               'description' => 'Línea de producción de ácido sulfúrico — tren 2'],
            ['code' => 'MOL_DOLOMITA',   'name' => 'Molienda Dolomita',         'description' => 'Línea de molienda de dolomita'],
            ['code' => 'VOLQUETA',       'name' => 'Volqueta',                  'description' => 'Flota de volquetas y transporte pesado'],
            ['code' => 'LAB_CALIDAD',    'name' => 'Laboratorio de Calidad',    'description' => 'Laboratorio de control de calidad e inspección'],
        ];

        foreach ($lines as $line) {
            DB::table('production_lines')->insertOrIgnore([
                'company_id'  => 1,
                'code'        => $line['code'],
                'name'        => $line['name'],
                'description' => $line['description'],
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $this->command->info('✅ 18 líneas de producción creadas para company_id=1 (HU-A1)');
    }

    /**
     * Seed asset_systems table for company_id = 1.
     */
    private function seedAssetSystems(): void
    {
        $companyExists = DB::table('companies')->where('id', 1)->exists();
        if (!$companyExists) {
            $this->command->warn('⚠️  No se encontró company_id=1. Se omite el seeder de sistemas de activos.');
            return;
        }

        $systems = [
            ['name' => 'Sistema Eléctrico',              'description' => 'Motores, tableros, transformadores, cableado y distribución eléctrica'],
            ['name' => 'Sistema Mecánico',               'description' => 'Rodamientos, transmisiones, acoplamientos, ejes y componentes mecánicos en general'],
            ['name' => 'Sistema Hidráulico',             'description' => 'Bombas hidráulicas, cilindros, válvulas y circuitos hidráulicos'],
            ['name' => 'Sistema Neumático',              'description' => 'Compresores, válvulas neumáticas, actuadores y circuitos de aire comprimido'],
            ['name' => 'Sistema de Lubricación',         'description' => 'Circuitos de lubricación centralizada, depósitos y puntos de engrase'],
            ['name' => 'Sistema de Control / PLC',       'description' => 'PLCs, HMIs, variadores de frecuencia y sistemas de automatización industrial'],
            ['name' => 'Sistema de Instrumentación',     'description' => 'Sensores, transmisores, analizadores y válvulas de control de proceso'],
            ['name' => 'Sistema de Agua y Refrigeración','description' => 'Torres de enfriamiento, circuitos de agua de proceso, intercambiadores'],
            ['name' => 'Sistema de Vapor',               'description' => 'Calderas, tuberías de vapor, trampas, válvulas y condensados'],
            ['name' => 'Sistema de Combustión',          'description' => 'Quemadores, hornos, calcinadores y sistemas de combustible'],
            ['name' => 'Sistema de Transporte',          'description' => 'Bandas transportadoras, elevadores de cangilones, tornillos sinfín y ductos'],
            ['name' => 'Sistema Civil / Estructural',    'description' => 'Estructuras metálicas, edificaciones, fundaciones y obras civiles'],
        ];

        foreach ($systems as $system) {
            DB::table('asset_systems')->insertOrIgnore([
                'company_id'  => 1,
                'name'        => $system['name'],
                'description' => $system['description'],
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $this->command->info('✅ 12 sistemas de activos creados para company_id=1');
    }
}
