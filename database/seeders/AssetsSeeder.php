<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetPriority;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener datos necesarios
        $company = Company::first();
        if (!$company) {
            $this->command->error('No hay empresas en la base de datos. Ejecuta CompaniesSeeder primero.');
            return;
        }

        $site = CompanySite::where('company_id', $company->id)->first();
        if (!$site) {
            $this->command->error('No hay sedes para la empresa. Ejecuta CompanySitesSeeder primero.');
            return;
        }

        $currency = Currency::where('code', 'COP')->first();
        $user = User::first();

        // Obtener categorías (códigos actualizados)
        $categoryBuilding    = AssetCategory::where('code', 'INFRASTRUCTURE')->first();
        $categoryEquipment   = AssetCategory::where('code', 'AUX_EQUIPMENT')->first();
        $categoryInstallation = AssetCategory::where('code', 'ELECTRICAL_SYSTEMS')->first();
        $categoryMachinery   = AssetCategory::where('code', 'PROCESS_MACHINERY')->first();
        $categoryHeavy       = AssetCategory::where('code', 'HEAVY_MACHINERY')->first();

        // Obtener estados
        $statusActive = AssetStatus::where('code', 'ACTIVE')->first();
        $statusMaintenance = AssetStatus::where('code', 'MAINTENANCE')->first();

        // Obtener prioridades
        $priorityCritical = AssetPriority::where('code', 'CRITICAL')->first();
        $priorityHigh = AssetPriority::where('code', 'HIGH')->first();
        $priorityMedium = AssetPriority::where('code', 'MEDIUM')->first();

        $this->command->info('Creando jerarquía de activos de ejemplo...');

        // ========================================
        // NIVEL 1: EDIFICIO PRINCIPAL (Raíz)
        // ========================================
        $edificioPrincipal = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'EDIF-001',
            'name' => 'Edificio Principal',
            'description' => 'Edificio principal de la planta de producción',
            'category_id' => $categoryBuilding->id,
            'status_id' => $statusActive->id,
            'priority_id' => $priorityCritical->id,
            'parent_id' => null,
            'brand' => null,
            'model' => null,
            'serial_number' => null,
            'capacity' => 5000.00,
            'capacity_unit' => 'm²',
            'manufacturing_year' => 2018,
            'materials_used' => ['Concreto', 'Acero estructural', 'Vidrio'],
            'location_details' => 'Entrada principal, bloque A',
            'latitude' => 2.9273,
            'longitude' => -75.2819,
            'purchase_cost' => 2500000000.00,
            'currency_id' => $currency->id,
            'purchase_date' => '2018-03-15',
            'cost_center' => 'INFRAESTRUCTURA',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("✅ Creado: {$edificioPrincipal->code} - {$edificioPrincipal->name}");

        // ========================================
        // NIVEL 2: RECEPCIÓN (Hijo de Edificio)
        // ========================================
        $recepcion = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'REC-001',
            'name' => 'Recepción',
            'description' => 'Área de recepción y atención al público',
            'category_id' => $categoryBuilding->id,
            'status_id' => $statusActive->id,
            'priority_id' => $priorityMedium->id,
            'parent_id' => $edificioPrincipal->id,
            'brand' => null,
            'model' => null,
            'serial_number' => null,
            'capacity' => 150.00,
            'capacity_unit' => 'm²',
            'manufacturing_year' => 2018,
            'materials_used' => ['Madera', 'Vidrio', 'Aluminio'],
            'location_details' => 'Primer piso, entrada principal',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("  ├─ Creado: {$recepcion->code} - {$recepcion->name}");

        // ========================================
        // NIVEL 3: COMPUTADORA (Nieto de Edificio, Hijo de Recepción)
        // ========================================
        $computadora = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'COMP-001',
            'name' => 'Computadora de Escritorio',
            'description' => 'Computadora Dell para recepcionista',
            'category_id' => $categoryEquipment->id,
            'status_id' => $statusActive->id,
            'priority_id' => $priorityHigh->id,
            'parent_id' => $recepcion->id,
            'brand' => 'Dell',
            'model' => 'OptiPlex 7090',
            'serial_number' => 'DELL-SVC-7090-2024',
            'capacity' => null,
            'capacity_unit' => null,
            'manufacturing_year' => 2024,
            'materials_used' => ['Aluminio', 'Plástico', 'Silicio'],
            'location_details' => 'Escritorio de recepción',
            'purchase_cost' => 3500000.00,
            'currency_id' => $currency->id,
            'purchase_date' => '2024-01-10',
            'cost_center' => 'ADMINISTRACION',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("    └─ Creado: {$computadora->code} - {$computadora->name}");

        // Agregar especificaciones a la computadora
        $computadora->specifications()->createMany([
            [
                'spec_key' => 'processor',
                'spec_value' => 'Intel Core i7-11700',
                'spec_unit' => null,
                'spec_type' => 'text',
                'display_order' => 1
            ],
            [
                'spec_key' => 'ram',
                'spec_value' => '16',
                'spec_unit' => 'GB',
                'spec_type' => 'number',
                'display_order' => 2
            ],
            [
                'spec_key' => 'storage',
                'spec_value' => '512',
                'spec_unit' => 'GB SSD',
                'spec_type' => 'number',
                'display_order' => 3
            ],
            [
                'spec_key' => 'operating_system',
                'spec_value' => 'Windows 11 Pro',
                'spec_unit' => null,
                'spec_type' => 'text',
                'display_order' => 4
            ]
        ]);

        // ========================================
        // NIVEL 4: PERIFÉRICOS (Bisnietos)
        // ========================================
        $monitor = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'MON-300',
            'name' => 'Monitor Samsung',
            'description' => 'Monitor LED 24 pulgadas',
            'category_id' => $categoryEquipment->id,
            'status_id' => $statusActive->id,
            'priority_id' => $priorityMedium->id,
            'parent_id' => $computadora->id,
            'brand' => 'Samsung',
            'model' => 'S24R350',
            'serial_number' => 'SAM-MON-2024-300',
            'capacity' => 24.00,
            'capacity_unit' => 'pulgadas',
            'manufacturing_year' => 2024,
            'location_details' => 'Escritorio recepción',
            'purchase_cost' => 450000.00,
            'currency_id' => $currency->id,
            'purchase_date' => '2024-01-10',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("       ├─ Creado: {$monitor->code} - {$monitor->name}");

        $monitor->specifications()->createMany([
            ['spec_key' => 'resolution', 'spec_value' => '1920x1080', 'spec_unit' => 'px', 'spec_type' => 'text', 'display_order' => 1],
            ['spec_key' => 'refresh_rate', 'spec_value' => '75', 'spec_unit' => 'Hz', 'spec_type' => 'number', 'display_order' => 2]
        ]);

        $teclado = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'TEC-4500',
            'name' => 'Teclado HP',
            'description' => 'Teclado inalámbrico',
            'category_id' => $categoryEquipment->id,
            'status_id' => $statusActive->id,
            'priority_id' => $priorityMedium->id,
            'parent_id' => $computadora->id,
            'brand' => 'HP',
            'model' => 'K5510',
            'serial_number' => 'HP-TEC-K5510-2024',
            'manufacturing_year' => 2024,
            'purchase_cost' => 120000.00,
            'currency_id' => $currency->id,
            'purchase_date' => '2024-01-10',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("       ├─ Creado: {$teclado->code} - {$teclado->name}");

        $cpu = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'CPU-15Q',
            'name' => 'CPU Lenovo',
            'description' => 'Unidad central de procesamiento',
            'category_id' => $categoryEquipment->id,
            'status_id' => $statusActive->id,
            'priority_id' => $priorityHigh->id,
            'parent_id' => $computadora->id,
            'brand' => 'Lenovo',
            'model' => 'ThinkCentre M720',
            'serial_number' => 'LEN-CPU-M720-2024',
            'manufacturing_year' => 2024,
            'purchase_cost' => 2800000.00,
            'currency_id' => $currency->id,
            'purchase_date' => '2024-01-10',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("       └─ Creado: {$cpu->code} - {$cpu->name}");

        // ========================================
        // NIVEL 2: TABLERO PRINCIPAL (Hermano de Recepción)
        // ========================================
        $tablero = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'TB-001',
            'name' => 'Tablero Principal',
            'description' => 'Tablero de distribución eléctrica principal',
            'category_id' => $categoryInstallation->id,
            'status_id' => $statusActive->id,
            'priority_id' => $priorityCritical->id,
            'parent_id' => $edificioPrincipal->id,
            'brand' => 'ABB',
            'model' => '2020',
            'serial_number' => 'ABB-S468',
            'capacity' => 250.00,
            'capacity_unit' => 'kVA',
            'manufacturing_year' => 2020,
            'materials_used' => ['Cobre', 'Aluminio', 'Acero inoxidable'],
            'location_details' => 'Primer piso, sala de máquinas',
            'latitude' => 2.9275,
            'longitude' => -75.2821,
            'purchase_cost' => 15000000.00,
            'currency_id' => $currency->id,
            'purchase_date' => '2020-01-15',
            'cost_center' => 'MANTENIMIENTO',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("  ├─ Creado: {$tablero->code} - {$tablero->name}");

        $tablero->specifications()->createMany([
            ['spec_key' => 'voltage', 'spec_value' => '220', 'spec_unit' => 'V', 'spec_type' => 'number', 'display_order' => 1],
            ['spec_key' => 'frequency', 'spec_value' => '60', 'spec_unit' => 'Hz', 'spec_type' => 'number', 'display_order' => 2],
            ['spec_key' => 'phases', 'spec_value' => '3', 'spec_unit' => null, 'spec_type' => 'number', 'display_order' => 3],
            ['spec_key' => 'protection_rating', 'spec_value' => 'IP65', 'spec_unit' => null, 'spec_type' => 'text', 'display_order' => 4]
        ]);

        // ========================================
        // NIVEL 3: CORREDORA ELÉCTRICA (Hijo de Tablero)
        // ========================================
        $corredora = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'CE-4Y57B437',
            'name' => 'Corredora Eléctrica',
            'description' => 'Máquina corredora eléctrica industrial',
            'category_id' => $categoryMachinery->id,
            'status_id' => $statusMaintenance->id,
            'priority_id' => $priorityHigh->id,
            'parent_id' => $tablero->id,
            'brand' => 'Siemens',
            'model' => 'SE-2023',
            'serial_number' => 'SIE-987654',
            'capacity' => 5.5,
            'capacity_unit' => 'kW',
            'manufacturing_year' => 2023,
            'materials_used' => ['Acero', 'Aluminio', 'Cobre'],
            'location_details' => 'Área de producción, línea 1',
            'latitude' => 2.9276,
            'longitude' => -75.2822,
            'purchase_cost' => 8500000.00,
            'currency_id' => $currency->id,
            'purchase_date' => '2023-05-10',
            'cost_center' => 'PRODUCCION',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("    └─ Creado: {$corredora->code} - {$corredora->name}");

        $corredora->specifications()->createMany([
            ['spec_key' => 'voltage', 'spec_value' => '220', 'spec_unit' => 'V', 'spec_type' => 'number', 'display_order' => 1],
            ['spec_key' => 'power', 'spec_value' => '5.5', 'spec_unit' => 'kW', 'spec_type' => 'number', 'display_order' => 2],
            ['spec_key' => 'rpm', 'spec_value' => '1450', 'spec_unit' => 'RPM', 'spec_type' => 'number', 'display_order' => 3],
            ['spec_key' => 'noise_level', 'spec_value' => '75', 'spec_unit' => 'dB', 'spec_type' => 'number', 'display_order' => 4]
        ]);

        // ========================================
        // NIVEL 2: BOMBA CENTRIFUGA (Hermano de Tablero y Recepción)
        // ========================================
        $bomba = Asset::create([
            'company_id' => $company->id,
            'company_site_id' => $site->id,
            'code' => 'MOTOBOMBA',
            'name' => 'Bomba Centrífuga Motobomba',
            'description' => 'Bomba centrífuga para sistema de agua',
            'category_id' => $categoryMachinery->id,
            'status_id' => $statusActive->id,
            'priority_id' => $priorityCritical->id,
            'parent_id' => $edificioPrincipal->id,
            'brand' => 'Grundfos',
            'model' => 'CR-64',
            'serial_number' => 'GRU-CR64-2022',
            'capacity' => 100.00,
            'capacity_unit' => 'm³/h',
            'manufacturing_year' => 2022,
            'materials_used' => ['Acero inoxidable', 'Bronce', 'Caucho'],
            'location_details' => 'Cuarto de máquinas, subsuelo',
            'latitude' => 2.9274,
            'longitude' => -75.2820,
            'purchase_cost' => 12000000.00,
            'currency_id' => $currency->id,
            'purchase_date' => '2022-08-20',
            'cost_center' => 'MANTENIMIENTO',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $this->command->info("  └─ Creado: {$bomba->code} - {$bomba->name}");

        $bomba->specifications()->createMany([
            ['spec_key' => 'pressure', 'spec_value' => '10', 'spec_unit' => 'bar', 'spec_type' => 'number', 'display_order' => 1],
            ['spec_key' => 'flow_rate', 'spec_value' => '100', 'spec_unit' => 'm³/h', 'spec_type' => 'number', 'display_order' => 2],
            ['spec_key' => 'power', 'spec_value' => '15', 'spec_unit' => 'kW', 'spec_type' => 'number', 'display_order' => 3],
            ['spec_key' => 'voltage', 'spec_value' => '380', 'spec_unit' => 'V', 'spec_type' => 'number', 'display_order' => 4],
            ['spec_key' => 'efficiency', 'spec_value' => '85', 'spec_unit' => '%', 'spec_type' => 'number', 'display_order' => 5]
        ]);

        // ========================================
        // MAQUINARIA AMARILLA (para inspecciones preoperacionales)
        // ========================================
        if ($categoryHeavy) {
            $lineaMaqAmarilla = DB::table('production_lines')
                ->where('company_id', $company->id)
                ->where('code', 'MAQ_AMARILLA')
                ->first();

            $heavyAssets = [
                [
                    'code'  => 'CAR-001',
                    'name'  => 'Cargador Frontal CAT 950',
                    'brand' => 'Caterpillar',
                    'model' => '950 GC',
                    'serial_number' => 'CAT-950GC-001',
                    'description' => 'Cargador frontal de ruedas para manejo de materiales a granel',
                    'manufacturing_year' => 2021,
                    'purchase_cost' => 450000000.00,
                    'purchase_date' => '2021-06-15',
                    'capacity' => 3.1,
                    'capacity_unit' => 'm³',
                ],
                [
                    'code'  => 'CAR-002',
                    'name'  => 'Cargador Frontal Komatsu WA380',
                    'brand' => 'Komatsu',
                    'model' => 'WA380-8',
                    'serial_number' => 'KOM-WA380-001',
                    'description' => 'Cargador frontal de ruedas, línea de proceso sulfúrico',
                    'manufacturing_year' => 2020,
                    'purchase_cost' => 420000000.00,
                    'purchase_date' => '2020-03-10',
                    'capacity' => 2.9,
                    'capacity_unit' => 'm³',
                ],
                [
                    'code'  => 'MON-001',
                    'name'  => 'Montacargas Toyota 8FG25',
                    'brand' => 'Toyota',
                    'model' => '8FG25',
                    'serial_number' => 'TOY-8FG25-001',
                    'description' => 'Montacargas a gas para manejo de estibas en almacén',
                    'manufacturing_year' => 2022,
                    'purchase_cost' => 85000000.00,
                    'purchase_date' => '2022-09-20',
                    'capacity' => 2500.00,
                    'capacity_unit' => 'kg',
                ],
                [
                    'code'  => 'MON-002',
                    'name'  => 'Montacargas Clark C25',
                    'brand' => 'Clark',
                    'model' => 'C25',
                    'serial_number' => 'CLK-C25-001',
                    'description' => 'Montacargas eléctrico para operaciones internas de planta',
                    'manufacturing_year' => 2023,
                    'purchase_cost' => 75000000.00,
                    'purchase_date' => '2023-02-14',
                    'capacity' => 2500.00,
                    'capacity_unit' => 'kg',
                ],
                [
                    'code'  => 'RET-001',
                    'name'  => 'Retroexcavadora JCB 3CX',
                    'brand' => 'JCB',
                    'model' => '3CX',
                    'serial_number' => 'JCB-3CX-001',
                    'description' => 'Retroexcavadora para obras civiles y mantenimiento de vías internas',
                    'manufacturing_year' => 2019,
                    'purchase_cost' => 280000000.00,
                    'purchase_date' => '2019-11-05',
                    'capacity' => null,
                    'capacity_unit' => null,
                ],
            ];

            foreach ($heavyAssets as $ha) {
                $asset = Asset::firstOrCreate(
                    ['code' => $ha['code'], 'company_id' => $company->id],
                    [
                        'company_site_id'     => $site->id,
                        'name'                => $ha['name'],
                        'description'         => $ha['description'],
                        'category_id'         => $categoryHeavy->id,
                        'status_id'           => $statusActive->id,
                        'priority_id'         => $priorityHigh->id,
                        'production_line_id'  => $lineaMaqAmarilla?->id,
                        'parent_id'           => null,
                        'brand'               => $ha['brand'],
                        'model'               => $ha['model'],
                        'serial_number'       => $ha['serial_number'],
                        'capacity'            => $ha['capacity'],
                        'capacity_unit'       => $ha['capacity_unit'],
                        'manufacturing_year'  => $ha['manufacturing_year'],
                        'purchase_cost'       => $ha['purchase_cost'],
                        'currency_id'         => $currency?->id,
                        'purchase_date'       => $ha['purchase_date'],
                        'cost_center'         => 'MAQ_AMARILLA',
                        'is_active'           => true,
                        'created_by'          => $user->id,
                        'updated_by'          => $user->id,
                    ]
                );
                $this->command->info("✅ Creado: {$asset->code} - {$asset->name}");
            }
        } else {
            $this->command->warn('⚠️  Categoría HEAVY_MACHINERY no encontrada. Ejecuta AssetCatalogsSeeder primero.');
        }

        // ========================================
        // ASIGNAR USUARIOS A ACTIVOS
        // ========================================
        if ($user) {
            $this->command->info("\nAsignando usuarios a activos críticos...");

            // Asignar responsable al tablero
            DB::table('asset_users')->insert([
                'asset_id' => $tablero->id,
                'user_id' => $user->id,
                'role' => 'responsible',
                'assigned_at' => now(),
                'assigned_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Asignar operador a la bomba
            DB::table('asset_users')->insert([
                'asset_id' => $bomba->id,
                'user_id' => $user->id,
                'role' => 'operator',
                'assigned_at' => now(),
                'assigned_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Asignar supervisor a la corredora
            DB::table('asset_users')->insert([
                'asset_id' => $corredora->id,
                'user_id' => $user->id,
                'role' => 'supervisor',
                'assigned_at' => now(),
                'assigned_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->command->info("✅ 3 usuarios asignados a activos críticos");
        }

        // ========================================
        // RESUMEN
        // ========================================
        $this->command->info("\n" . str_repeat('=', 60));
        $this->command->info('📊 RESUMEN DE DATOS DE PRUEBA CREADOS:');
        $this->command->info(str_repeat('=', 60));
        $this->command->info("✅ 9 activos creados con jerarquía de 4 niveles");
        $this->command->info("✅ 15 especificaciones técnicas agregadas");
        $this->command->info("✅ 3 usuarios asignados a activos críticos");
        $this->command->info("\n📁 JERARQUÍA CREADA:");
        $this->command->info("EDIFICIO PRINCIPAL");
        $this->command->info("  ├─ RECEPCIÓN");
        $this->command->info("  │   └─ COMPUTADORA");
        $this->command->info("  │       ├─ Monitor Samsung");
        $this->command->info("  │       ├─ Teclado HP");
        $this->command->info("  │       └─ CPU Lenovo");
        $this->command->info("  ├─ TABLERO PRINCIPAL");
        $this->command->info("  │   └─ CORREDORA ELÉCTRICA");
        $this->command->info("  └─ BOMBA CENTRÍFUGA");
        $this->command->info(str_repeat('=', 60));
    }
}
