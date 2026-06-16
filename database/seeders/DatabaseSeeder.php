<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ejecutar seeders en orden de dependencias
        $this->call([
            // 1. Datos geográficos y tipos básicos (sin dependencias)
            GeographicDataSeeder::class,
            ContactTypesSeeder::class,
            DocumentTypesSeeder::class,
            
            // 2. Datos del sistema (monedas, configuraciones, catálogos)
            SystemDataSeeder::class,
            
            // 3. Módulos y planes comerciales
            ModulesAndPlansSeeder::class,
            
            // 4. Clasificación de empresas y tipos organizacionales
            CompanySizesSeeder::class,
            OrganizationalDataSeeder::class, // Site types
            
            // 5. Roles y permisos del sistema (antes de crear usuarios de prueba)
            RolesAndPermissionsSeeder::class,
            
            // 6. Datos de prueba (dependen de roles y empresas)
            TestDataSeeder::class,
            
            // 7. Sedes de empresa (dependen de empresas y site types)
            CompanySitesSeeder::class,
            
            // 8. Catálogos de activos (sin dependencias adicionales)
            AssetCatalogsSeeder::class,
            MaintenanceTypesSeeder::class,
            
            // 9. Datos de prueba de activos (dependen de empresas, sedes y catálogos)
            AssetsSeeder::class,

            // 10. Módulo de Solicitudes de Trabajo (depende de módulos, permisos y roles)
            WorkRequestsModuleSeeder::class,

            // 11. Catálogos de Solicitudes de Trabajo (dependen de empresas y categorías de activos)
            WorkRequestsCatalogsSeeder::class,

            // 12. Módulo de Inventario y Almacenes
            InventoryModuleSeeder::class,
            MaterialCategorySeeder::class,
            ComponentsModuleSeeder::class,

            // 13. Módulo de Órdenes de Trabajo
            WorkOrdersModuleSeeder::class,

            // 14. Módulo de Medidores de Activos
            AssetMetersModuleSeeder::class,

            // 15. Módulo de Planes de Mantenimiento
            MaintenancePlansModuleSeeder::class,

            // 16. Módulo de Inspecciones Preoperacionales
            InspectionShiftsSeeder::class,
            InspectionTemplatesSeeder::class,

            // 17. Módulo de Proyectos y Montajes
            ProjectsModuleSeeder::class,
        ]);
    }
}
