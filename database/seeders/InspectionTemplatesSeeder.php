<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InspectionTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $companyExists = DB::table('companies')->where('id', 1)->exists();
        if (!$companyExists) {
            $this->command->warn('⚠️  No se encontró company_id=1. Se omite el seeder de plantillas de inspección.');
            return;
        }

        $category = DB::table('asset_categories')->where('code', 'HEAVY_MACHINERY')->first();
        $categoryId = $category?->id;

        // ─── PLANTILLA: CARGADOR ─────────────────────────────────────────────
        $cargadorId = DB::table('inspection_templates')->insertGetId([
            'company_id'  => 1,
            'category_id' => $categoryId,
            'name'        => 'Inspección Preoperacional — Cargador',
            'description' => 'Checklist preoperacional para cargadores de maquinaria amarilla',
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->seedSections($cargadorId, $this->getCargadorSections());

        // ─── PLANTILLA: MONTACARGAS ──────────────────────────────────────────
        $montacargasId = DB::table('inspection_templates')->insertGetId([
            'company_id'  => 1,
            'category_id' => $categoryId,
            'name'        => 'Inspección Preoperacional — Montacargas',
            'description' => 'Checklist preoperacional para montacargas',
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->seedSections($montacargasId, $this->getMontacargasSections());

        $this->command->info('✅ 2 plantillas de inspección preoperacional creadas (Cargador y Montacargas)');
    }

    private function seedSections(int $templateId, array $sections): void
    {
        foreach ($sections as $order => $section) {
            $sectionId = DB::table('inspection_sections')->insertGetId([
                'template_id'      => $templateId,
                'name'             => $section['name'],
                'order_index'      => $order,
                'response_options' => json_encode($section['options']),
                'has_observation'  => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($section['items'] as $itemOrder => $item) {
                DB::table('inspection_items')->insert([
                    'section_id'           => $sectionId,
                    'name'                 => $item['name'],
                    'order_index'          => $itemOrder,
                    'is_required'          => true,
                    'is_active'            => true,
                    'non_conformant_value' => $item['non_conformant'] ?? null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        }
    }

    private function getCargadorSections(): array
    {
        return [
            [
                'name'    => '1. Niveles',
                'options' => ['BAJO', 'ALTO', 'NORMAL'],
                'items'   => [
                    ['name' => 'Nivel de Aceite de Motor',          'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Aceite Hidráulico',        'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Aceite de la Transmisión', 'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de líquido de frenos',        'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Refrigerante',             'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Combustible',              'non_conformant' => 'BAJO'],
                ],
            ],
            [
                'name'    => '2. Pérdidas de líquidos',
                'options' => ['SI', 'NO'],
                'items'   => [
                    ['name' => 'Fuga aceite de Motor',           'non_conformant' => 'SI'],
                    ['name' => 'Fugas de Aceite Hidráulico',     'non_conformant' => 'SI'],
                    ['name' => 'Fugas de Aceite de transmisión', 'non_conformant' => 'SI'],
                    ['name' => 'Fugas de líquidos de frenos',    'non_conformant' => 'SI'],
                    ['name' => 'Fugas de Refrigerante',          'non_conformant' => 'SI'],
                ],
            ],
            [
                'name'    => '3. Tablero de Control',
                'options' => ['Operativo', 'No operativo'],
                'items'   => [
                    ['name' => 'Luces de Tablero',               'non_conformant' => 'No operativo'],
                    ['name' => 'Horómetro',                      'non_conformant' => 'No operativo'],
                    ['name' => 'Indicador de Presión de Aceite', 'non_conformant' => 'No operativo'],
                    ['name' => 'Indicador de Temperatura',       'non_conformant' => 'No operativo'],
                    ['name' => 'Indicador de presión de aire',   'non_conformant' => 'No operativo'],
                ],
            ],
            [
                'name'    => '4. Seguridad Pasiva',
                'options' => ['Cumple', 'No cumple'],
                'items'   => [
                    ['name' => 'Cinturones de Seguridad',              'non_conformant' => 'No cumple'],
                    ['name' => 'Cabina (Vidrios, incluyendo parabrisas)', 'non_conformant' => 'No cumple'],
                    ['name' => 'Espejo Lateral Derecho',               'non_conformant' => 'No cumple'],
                    ['name' => 'Espejo Lateral Izquierdo',             'non_conformant' => 'No cumple'],
                    ['name' => 'Espejo Retrovisor',                    'non_conformant' => 'No cumple'],
                    ['name' => 'Bocina',                               'non_conformant' => 'No cumple'],
                    ['name' => 'Extintor',                             'non_conformant' => 'No cumple'],
                ],
            ],
            [
                'name'    => '5. Luces o luminarias',
                'options' => ['SI', 'NO'],
                'items'   => [
                    ['name' => 'Luces Delanteras',  'non_conformant' => 'NO'],
                    ['name' => 'Luces Traseras',    'non_conformant' => 'NO'],
                    ['name' => 'Luces Medias',      'non_conformant' => 'NO'],
                    ['name' => 'Luces Altas',       'non_conformant' => 'NO'],
                    ['name' => 'Direccionales',     'non_conformant' => 'NO'],
                ],
            ],
            [
                'name'    => '6. Seguridad Activa',
                'options' => ['Operativo', 'No operativo'],
                'items'   => [
                    ['name' => 'Estado de Aguilón (levantar y bajar)', 'non_conformant' => 'No operativo'],
                    ['name' => 'Estado de Balde (abrir y cerrar)',     'non_conformant' => 'No operativo'],
                    ['name' => 'Estado Suspensión Delantera',          'non_conformant' => 'No operativo'],
                    ['name' => 'Estado Suspensión Trasera',            'non_conformant' => 'No operativo'],
                ],
            ],
        ];
    }

    private function getMontacargasSections(): array
    {
        return [
            [
                'name'    => '2. Niveles',
                'options' => ['BAJO', 'ALTO', 'NORMAL'],
                'items'   => [
                    ['name' => 'Nivel de Aceite de Motor',              'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Aceite Hidráulico',            'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Aceite de la Transmisión',     'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Aceite de la Servotransmisión','non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de líquido de frenos',            'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Refrigerante',                 'non_conformant' => 'BAJO'],
                    ['name' => 'Nivel de Combustible',                  'non_conformant' => 'BAJO'],
                ],
            ],
            [
                'name'    => '3. Pérdidas de líquidos',
                'options' => ['SI', 'NO'],
                'items'   => [
                    ['name' => 'Fuga aceite de Motor',                  'non_conformant' => 'SI'],
                    ['name' => 'Fugas de Aceite Hidráulico',            'non_conformant' => 'SI'],
                    ['name' => 'Fugas de Aceite de transmisión',        'non_conformant' => 'SI'],
                    ['name' => 'Fugas de Aceite de la Servotransmisión','non_conformant' => 'SI'],
                    ['name' => 'Fugas de líquidos de frenos',           'non_conformant' => 'SI'],
                    ['name' => 'Fugas de Refrigerante',                 'non_conformant' => 'SI'],
                ],
            ],
            [
                'name'    => '4. Tablero de Control',
                'options' => ['Operativo', 'No operativo'],
                'items'   => [
                    ['name' => 'Luces de Tablero',               'non_conformant' => 'No operativo'],
                    ['name' => 'Horómetro',                      'non_conformant' => 'No operativo'],
                    ['name' => 'Indicador de Presión de Aceite', 'non_conformant' => 'No operativo'],
                    ['name' => 'Indicador de Temperatura',       'non_conformant' => 'No operativo'],
                    ['name' => 'Indicador de presión de aire',   'non_conformant' => 'No operativo'],
                ],
            ],
            [
                'name'    => '5. Seguridad Pasiva',
                'options' => ['Cumple', 'No cumple'],
                'items'   => [
                    ['name' => 'Cinturones de Seguridad',  'non_conformant' => 'No cumple'],
                    ['name' => 'Espejo Lateral Derecho',   'non_conformant' => 'No cumple'],
                    ['name' => 'Espejo Lateral Izquierdo', 'non_conformant' => 'No cumple'],
                    ['name' => 'Espejo Retrovisor',        'non_conformant' => 'No cumple'],
                    ['name' => 'Bocina',                   'non_conformant' => 'No cumple'],
                    ['name' => 'Extintor',                 'non_conformant' => 'No cumple'],
                ],
            ],
            [
                'name'    => '6. Seguridad Activa',
                'options' => ['Operativo', 'No operativo'],
                'items'   => [
                    ['name' => 'Estado Suspensión Delantera',          'non_conformant' => 'No operativo'],
                    ['name' => 'Estado de Mástil (levantar y bajar)',  'non_conformant' => 'No operativo'],
                    ['name' => 'Estado Joystick o palancas de control','non_conformant' => 'No operativo'],
                    ['name' => 'Estado de uñas',                       'non_conformant' => 'No operativo'],
                ],
            ],
            [
                'name'    => '5. Luces o luminarias',
                'options' => ['Cumple', 'No cumple'],
                'items'   => [
                    ['name' => 'Luces Delanteras', 'non_conformant' => 'No cumple'],
                    ['name' => 'Luces Traseras',   'non_conformant' => 'No cumple'],
                    ['name' => 'Luces Medias',     'non_conformant' => 'No cumple'],
                    ['name' => 'Luces Altas',      'non_conformant' => 'No cumple'],
                    ['name' => 'Direccionales',    'non_conformant' => 'No cumple'],
                ],
            ],
            [
                'name'    => '7. Estado Llantas',
                'options' => ['Cumple', 'No cumple'],
                'items'   => [
                    ['name' => 'Delantera Derecha',  'non_conformant' => 'No cumple'],
                    ['name' => 'Delantera Izquierda','non_conformant' => 'No cumple'],
                    ['name' => 'Trasera Derecha',    'non_conformant' => 'No cumple'],
                    ['name' => 'Trasera Izquierda',  'non_conformant' => 'No cumple'],
                ],
            ],
        ];
    }
}
