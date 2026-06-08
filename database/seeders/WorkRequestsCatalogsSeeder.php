<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkRequestsCatalogsSeeder extends Seeder
{
    /**
     * Seed Work Requests catalog tables (tags, checklist templates).
     */
    public function run(): void
    {
        $this->command->info('🏷️ Iniciando seeder de catálogos de Solicitudes de Trabajo...');

        // Verificar que existe al menos una empresa
        $company = DB::table('companies')->first();
        
        if (!$company) {
            $this->command->warn('⚠️ No hay empresas en la base de datos. Saltando creación de catálogos.');
            return;
        }

        $this->seedTags($company->id);
        $this->seedChecklistTemplates($company->id);

        $this->command->info('✅ Catálogos de Solicitudes de Trabajo creados exitosamente');
    }

    /**
     * Seed work_request_tags table.
     */
    private function seedTags(int $companyId): void
    {
        $tags = [
            [
                'company_id' => $companyId,
                'name' => 'Urgente',
                'slug' => 'urgente',
                'color' => '#EF4444',
                'description' => 'Solicitudes que requieren atención inmediata',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Garantía',
                'slug' => 'garantia',
                'color' => '#3B82F6',
                'description' => 'Solicitudes relacionadas con equipos en garantía',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Recurrente',
                'slug' => 'recurrente',
                'color' => '#F59E0B',
                'description' => 'Solicitudes que se repiten con frecuencia',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Mejora',
                'slug' => 'mejora',
                'color' => '#10B981',
                'description' => 'Solicitudes de mejora o optimización',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Seguridad',
                'slug' => 'seguridad',
                'color' => '#DC2626',
                'description' => 'Solicitudes relacionadas con seguridad laboral',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Preventivo',
                'slug' => 'preventivo',
                'color' => '#8B5CF6',
                'description' => 'Solicitudes de mantenimiento preventivo',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Correctivo',
                'slug' => 'correctivo',
                'color' => '#EC4899',
                'description' => 'Solicitudes de mantenimiento correctivo',
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Externo',
                'slug' => 'externo',
                'color' => '#6366F1',
                'description' => 'Requiere servicio técnico externo',
                'is_active' => true,
            ],
        ];

        foreach ($tags as $tag) {
            DB::table('work_request_tags')->insertOrIgnore($tag);
        }

        $this->command->info('✅ 8 etiquetas creadas');
    }

    /**
     * Seed work_request_checklist_templates table.
     */
    private function seedChecklistTemplates(int $companyId): void
    {
        // Obtener categorías de activos
        $categoryEquipment = DB::table('asset_categories')->where('code', 'EQUIPMENT')->first();
        $categoryMachinery = DB::table('asset_categories')->where('code', 'MACHINERY')->first();
        $categoryInstallation = DB::table('asset_categories')->where('code', 'INSTALLATION')->first();

        $templates = [];

        // === EQUIPOS - CORRECTIVO - ALTA ===
        if ($categoryEquipment) {
            $templates[] = [
                'company_id' => $companyId,
                'name' => 'Checklist Correctivo - Equipos (Alta Prioridad)',
                'description' => 'Lista de verificación para atender solicitudes correctivas urgentes en equipos',
                'checklist_items' => json_encode([
                    ['text' => 'Verificar condiciones de seguridad del área', 'is_required' => true, 'order' => 1],
                    ['text' => 'Documentar falla con fotografías', 'is_required' => true, 'order' => 2],
                    ['text' => 'Identificar causa raíz del problema', 'is_required' => true, 'order' => 3],
                    ['text' => 'Verificar disponibilidad de repuestos', 'is_required' => false, 'order' => 4],
                    ['text' => 'Estimar tiempo de reparación', 'is_required' => true, 'order' => 5],
                ]),
                'asset_category_id' => $categoryEquipment->id,
                'request_type' => 'corrective',
                'priority' => 'high',
                'is_mandatory' => true,
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // === EQUIPOS - PREVENTIVO - MEDIA ===
        if ($categoryEquipment) {
            $templates[] = [
                'company_id' => $companyId,
                'name' => 'Checklist Preventivo - Equipos',
                'description' => 'Lista de verificación para mantenimientos preventivos en equipos',
                'checklist_items' => json_encode([
                    ['text' => 'Limpieza general del equipo', 'is_required' => true, 'order' => 1],
                    ['text' => 'Lubricación de partes móviles', 'is_required' => true, 'order' => 2],
                    ['text' => 'Inspección visual de componentes', 'is_required' => true, 'order' => 3],
                    ['text' => 'Verificar conexiones eléctricas', 'is_required' => false, 'order' => 4],
                    ['text' => 'Actualizar registro de mantenimiento', 'is_required' => true, 'order' => 5],
                ]),
                'asset_category_id' => $categoryEquipment->id,
                'request_type' => 'preventive',
                'priority' => 'medium',
                'is_mandatory' => false,
                'display_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // === MAQUINARIA - CORRECTIVO - CRITICA ===
        if ($categoryMachinery) {
            $templates[] = [
                'company_id' => $companyId,
                'name' => 'Checklist Correctivo Crítico - Maquinaria',
                'description' => 'Lista de verificación para fallas críticas en maquinaria industrial',
                'checklist_items' => json_encode([
                    ['text' => 'Detener operaciones y aislar máquina', 'is_required' => true, 'order' => 1],
                    ['text' => 'Notificar a producción sobre parada', 'is_required' => true, 'order' => 2],
                    ['text' => 'Evaluar riesgo para personal', 'is_required' => true, 'order' => 3],
                    ['text' => 'Contactar fabricante si es necesario', 'is_required' => false, 'order' => 4],
                    ['text' => 'Preparar plan de contingencia', 'is_required' => true, 'order' => 5],
                ]),
                'asset_category_id' => $categoryMachinery->id,
                'request_type' => 'corrective',
                'priority' => 'critical',
                'is_mandatory' => true,
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // === INSTALACIONES - INSTALACION - MEDIA ===
        if ($categoryInstallation) {
            $templates[] = [
                'company_id' => $companyId,
                'name' => 'Checklist Instalación - Instalaciones Eléctricas',
                'description' => 'Lista de verificación para nuevas instalaciones eléctricas',
                'checklist_items' => json_encode([
                    ['text' => 'Verificar cumplimiento normativa eléctrica', 'is_required' => true, 'order' => 1],
                    ['text' => 'Revisar planos y especificaciones', 'is_required' => true, 'order' => 2],
                    ['text' => 'Inspeccionar calidad de materiales', 'is_required' => true, 'order' => 3],
                    ['text' => 'Pruebas de continuidad y aislamiento', 'is_required' => true, 'order' => 4],
                    ['text' => 'Documentar instalación con fotografías', 'is_required' => false, 'order' => 5],
                ]),
                'asset_category_id' => $categoryInstallation->id,
                'request_type' => 'improvement',
                'priority' => 'medium',
                'is_mandatory' => true,
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // === GENÉRICO - MEJORA - BAJA ===
        $templates[] = [
            'company_id' => $companyId,
            'name' => 'Checklist Mejoras Generales',
            'description' => 'Lista de verificación para propuestas de mejora en cualquier activo',
            'checklist_items' => json_encode([
                ['text' => 'Describir situación actual', 'is_required' => true, 'order' => 1],
                ['text' => 'Proponer solución de mejora', 'is_required' => true, 'order' => 2],
                ['text' => 'Estimar beneficios esperados', 'is_required' => false, 'order' => 3],
                ['text' => 'Evaluar costo-beneficio', 'is_required' => false, 'order' => 4],
            ]),
            'asset_category_id' => null, // Aplica a todas las categorías
            'request_type' => 'improvement',
            'priority' => 'low',
            'is_mandatory' => false,
            'display_order' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // === INSPECCIONES - TODAS LAS CATEGORÍAS ===
        $templates[] = [
            'company_id' => $companyId,
            'name' => 'Checklist Inspección General',
            'description' => 'Lista de verificación para inspecciones rutinarias de activos',
            'checklist_items' => json_encode([
                ['text' => 'Inspección visual externa', 'is_required' => true, 'order' => 1],
                ['text' => 'Verificar condiciones de operación', 'is_required' => true, 'order' => 2],
                ['text' => 'Revisar indicadores y controles', 'is_required' => true, 'order' => 3],
                ['text' => 'Identificar anomalías o desgastes', 'is_required' => true, 'order' => 4],
                ['text' => 'Registrar hallazgos con fotografías', 'is_required' => false, 'order' => 5],
            ]),
            'asset_category_id' => null,
            'request_type' => 'inspection',
            'priority' => null, // Aplica a todas las prioridades
            'is_mandatory' => false,
            'display_order' => 4,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($templates as $template) {
            DB::table('work_request_checklist_templates')->insert($template);
        }

        $this->command->info('✅ ' . count($templates) . ' plantillas de checklist creadas');
        $this->command->info('   - 1 plantilla para Equipos - Correctivo - Alta');
        $this->command->info('   - 1 plantilla para Equipos - Preventivo - Media');
        $this->command->info('   - 1 plantilla para Maquinaria - Correctivo - Crítica');
        $this->command->info('   - 1 plantilla para Instalaciones - Mejoras - Media');
        $this->command->info('   - 2 plantillas genéricas (Mejoras e Inspección)');
    }
}
