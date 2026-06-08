<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Crea categorías de materiales con estructura jerárquica:
     * - Materiales (Filtros, Lubricantes, Químicos, Eléctricos, Mecánicos)
     * - Herramientas (Manuales, Eléctricas, Medición, Seguridad)
     * - Repuestos (Rodamientos, Sellos, Correas, Piezas Mecánicas)
     */
    public function run(): void
    {
        $now = now();
        $companyId = 1; // Primera compañía
        $userId = 1; // Primer usuario admin

        // ========================================
        // 1. CATEGORÍAS PRINCIPALES (NIVEL 1)
        // ========================================
        $categories = [
            // Materiales
            [
                'id' => 1,
                'company_id' => $companyId,
                'code' => 'CAT-MAT',
                'name' => 'Materiales',
                'description' => 'Materiales de consumo y mantenimiento general',
                'parent_category_id' => null,
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Herramientas
            [
                'id' => 2,
                'company_id' => $companyId,
                'code' => 'CAT-HERR',
                'name' => 'Herramientas',
                'description' => 'Herramientas e instrumentos de trabajo',
                'parent_category_id' => null,
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Repuestos
            [
                'id' => 3,
                'company_id' => $companyId,
                'code' => 'CAT-REP',
                'name' => 'Repuestos',
                'description' => 'Repuestos y componentes de reemplazo',
                'parent_category_id' => null,
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ========================================
            // 2. SUBCATEGORÍAS DE MATERIALES (NIVEL 2)
            // ========================================
            [
                'id' => 4,
                'company_id' => $companyId,
                'code' => 'CAT-MAT-FIL',
                'name' => 'Filtros',
                'description' => 'Filtros de aire, aceite, combustible, hidráulicos',
                'parent_category_id' => 1, // Materiales
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'company_id' => $companyId,
                'code' => 'CAT-MAT-LUB',
                'name' => 'Lubricantes',
                'description' => 'Aceites, grasas y lubricantes industriales',
                'parent_category_id' => 1, // Materiales
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'company_id' => $companyId,
                'code' => 'CAT-MAT-QUI',
                'name' => 'Químicos',
                'description' => 'Productos químicos de limpieza y mantenimiento',
                'parent_category_id' => 1, // Materiales
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'company_id' => $companyId,
                'code' => 'CAT-MAT-ELE',
                'name' => 'Eléctricos',
                'description' => 'Materiales y componentes eléctricos',
                'parent_category_id' => 1, // Materiales
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 8,
                'company_id' => $companyId,
                'code' => 'CAT-MAT-MEC',
                'name' => 'Mecánicos',
                'description' => 'Materiales mecánicos varios (tornillería, remaches, etc)',
                'parent_category_id' => 1, // Materiales
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ========================================
            // 3. SUBCATEGORÍAS DE HERRAMIENTAS (NIVEL 2)
            // ========================================
            [
                'id' => 9,
                'company_id' => $companyId,
                'code' => 'CAT-HERR-MAN',
                'name' => 'Herramientas Manuales',
                'description' => 'Llaves, destornilladores, alicates, martillos, etc.',
                'parent_category_id' => 2, // Herramientas
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'company_id' => $companyId,
                'code' => 'CAT-HERR-ELE',
                'name' => 'Herramientas Eléctricas',
                'description' => 'Taladros, esmeriladoras, sierras eléctricas, etc.',
                'parent_category_id' => 2, // Herramientas
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 11,
                'company_id' => $companyId,
                'code' => 'CAT-HERR-MED',
                'name' => 'Instrumentos de Medición',
                'description' => 'Calibradores, micrómetros, multímetros, etc.',
                'parent_category_id' => 2, // Herramientas
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 12,
                'company_id' => $companyId,
                'code' => 'CAT-HERR-SEG',
                'name' => 'Equipos de Seguridad',
                'description' => 'EPP, cascos, guantes, gafas de protección, etc.',
                'parent_category_id' => 2, // Herramientas
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ========================================
            // 4. SUBCATEGORÍAS DE REPUESTOS (NIVEL 2)
            // ========================================
            [
                'id' => 13,
                'company_id' => $companyId,
                'code' => 'CAT-REP-ROD',
                'name' => 'Rodamientos',
                'description' => 'Rodamientos de bolas, rodillos, agujas, etc.',
                'parent_category_id' => 3, // Repuestos
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 14,
                'company_id' => $companyId,
                'code' => 'CAT-REP-SEL',
                'name' => 'Sellos',
                'description' => 'Sellos mecánicos, retenes, juntas, empaques',
                'parent_category_id' => 3, // Repuestos
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 15,
                'company_id' => $companyId,
                'code' => 'CAT-REP-COR',
                'name' => 'Correas',
                'description' => 'Correas de transmisión, en V, dentadas, etc.',
                'parent_category_id' => 3, // Repuestos
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 16,
                'company_id' => $companyId,
                'code' => 'CAT-REP-PME',
                'name' => 'Piezas Mecánicas',
                'description' => 'Engranajes, ejes, poleas, acoples, etc.',
                'parent_category_id' => 3, // Repuestos
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('material_categories')->insert($categories);

        $this->command->info('✅ 16 categorías creadas (3 principales + 13 subcategorías)');
        $this->command->info('   - Materiales (5 subcategorías)');
        $this->command->info('   - Herramientas (4 subcategorías)');
        $this->command->info('   - Repuestos (4 subcategorías)');
    }
}
