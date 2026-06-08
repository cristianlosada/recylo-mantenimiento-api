<?php

namespace App\Services;

use App\Models\InductionTemplate;
use App\Models\InductionTemplateSection;
use App\Models\InductionTemplateItem;
use App\Models\InductionProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class InductionTemplateService
{
    /**
     * Listar plantillas de la empresa
     */
    public function listTemplates(int $companyId, array $filters = []): Collection
    {
        $query = InductionTemplate::where('company_id', $companyId)
            ->with(['sections.items', 'creator', 'publisher']);

        // Filtrar por tipo
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtrar por estado activo
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filtrar por estado publicado
        if (isset($filters['is_published'])) {
            $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filtrar por versión
        if (isset($filters['version'])) {
            $query->where('version', $filters['version']);
        }

        // Ordenar
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->get();
    }

    /**
     * Obtener plantilla por ID
     */
    public function getTemplateById(int $templateId, int $companyId): ?InductionTemplate
    {
        return InductionTemplate::where('id', $templateId)
            ->where('company_id', $companyId)
            ->with(['sections.items', 'creator', 'publisher'])
            ->withCount('activeProcesses')
            ->first();
    }

    /**
     * Crear nueva plantilla
     */
    public function createTemplate(array $data, int $companyId, int $createdBy): InductionTemplate
    {
        return DB::transaction(function () use ($data, $companyId, $createdBy) {
            // Crear plantilla
            $template = InductionTemplate::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'version' => 1,
                'is_active' => $data['is_active'] ?? false,
                'is_published' => false,
                'created_by' => $createdBy,
            ]);

            // Crear secciones e items
            if (isset($data['sections']) && is_array($data['sections'])) {
                foreach ($data['sections'] as $sectionData) {
                    $this->createSection($template->id, $sectionData);
                }
            }

            return $template->load(['sections.items']);
        });
    }

    /**
     * Actualizar plantilla existente
     */
    public function updateTemplate(int $templateId, array $data, int $companyId): InductionTemplate
    {
        return DB::transaction(function () use ($templateId, $data, $companyId) {
            $template = InductionTemplate::where('id', $templateId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Actualizar datos básicos
            $template->update([
                'name' => $data['name'] ?? $template->name,
                'description' => $data['description'] ?? $template->description,
                'is_active' => $data['is_active'] ?? $template->is_active,
            ]);

            // Si hay procesos activos, no permitir cambios en secciones
            $hasActiveProcesses = InductionProcess::where('template_id', $template->id)
                ->whereIn('status', ['scheduled', 'sent', 'in_progress'])
                ->exists();

            if (!$hasActiveProcesses && isset($data['sections'])) {
                // Eliminar secciones antiguas
                InductionTemplateSection::where('template_id', $template->id)->delete();

                // Crear nuevas secciones
                foreach ($data['sections'] as $sectionData) {
                    $this->createSection($template->id, $sectionData);
                }
            }

            return $template->load(['sections.items']);
        });
    }

    /**
     * Publicar plantilla
     */
    public function publishTemplate(int $templateId, int $companyId, int $publishedBy): InductionTemplate
    {
        $template = InductionTemplate::where('id', $templateId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Verificar que tenga al menos una sección
        $sectionsCount = InductionTemplateSection::where('template_id', $template->id)->count();
        if ($sectionsCount === 0) {
            throw new \Exception('La plantilla debe tener al menos una sección para ser publicada');
        }

        // Verificar que cada sección tenga al menos un item
        $sectionsWithoutItems = InductionTemplateSection::where('template_id', $template->id)
            ->whereDoesntHave('items')
            ->count();

        if ($sectionsWithoutItems > 0) {
            throw new \Exception('Todas las secciones deben tener al menos un item');
        }

        $template->update([
            'is_published' => true,
            'published_by' => $publishedBy,
            'published_at' => now(),
        ]);

        return $template->load(['sections.items']);
    }

    /**
     * Despublicar plantilla
     */
    public function unpublishTemplate(int $templateId, int $companyId): InductionTemplate
    {
        $template = InductionTemplate::where('id', $templateId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Verificar que no tenga procesos activos
        $hasActiveProcesses = InductionProcess::where('template_id', $template->id)
            ->whereIn('status', ['scheduled', 'sent', 'in_progress'])
            ->exists();

        if ($hasActiveProcesses) {
            throw new \Exception('No se puede despublicar una plantilla con procesos activos');
        }

        $template->update([
            'is_published' => false,
            'published_by' => null,
            'published_at' => null,
        ]);

        return $template;
    }

    /**
     * Activar/Desactivar plantilla
     */
    public function toggleTemplateStatus(int $templateId, int $companyId, bool $isActive): InductionTemplate
    {
        $template = InductionTemplate::where('id', $templateId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Si se está desactivando, verificar que no tenga procesos activos
        if (!$isActive) {
            $hasActiveProcesses = InductionProcess::where('template_id', $template->id)
                ->whereIn('status', ['scheduled', 'sent', 'in_progress'])
                ->exists();

            if ($hasActiveProcesses) {
                throw new \Exception('No se puede desactivar una plantilla con procesos activos');
            }
        }

        $template->update(['is_active' => $isActive]);

        return $template;
    }

    /**
     * Eliminar plantilla
     */
    public function deleteTemplate(int $templateId, int $companyId): bool
    {
        return DB::transaction(function () use ($templateId, $companyId) {
            $template = InductionTemplate::where('id', $templateId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Verificar que no tenga procesos asociados
            $hasProcesses = InductionProcess::where('template_id', $template->id)->exists();

            if ($hasProcesses) {
                throw new \Exception('No se puede eliminar una plantilla con procesos asociados');
            }

            // Eliminar secciones e items (cascade)
            InductionTemplateSection::where('template_id', $template->id)->delete();

            // Eliminar plantilla
            return $template->delete();
        });
    }

    /**
     * Duplicar plantilla
     */
    public function duplicateTemplate(int $templateId, int $companyId, int $createdBy, ?string $newName = null): InductionTemplate
    {
        return DB::transaction(function () use ($templateId, $companyId, $createdBy, $newName) {
            $originalTemplate = InductionTemplate::where('id', $templateId)
                ->where('company_id', $companyId)
                ->with(['sections.items'])
                ->firstOrFail();

            // Crear nueva plantilla
            $newTemplate = InductionTemplate::create([
                'company_id' => $companyId,
                'name' => $newName ?? ($originalTemplate->name . ' (Copia)'),
                'description' => $originalTemplate->description,
                'type' => $originalTemplate->type,
                'version' => 1,
                'is_active' => false,
                'is_published' => false,
                'created_by' => $createdBy,
            ]);

            // Duplicar secciones e items
            foreach ($originalTemplate->sections as $section) {
                $newSection = InductionTemplateSection::create([
                    'template_id' => $newTemplate->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'order' => $section->order,
                    'is_required' => $section->is_required,
                ]);

                foreach ($section->items as $item) {
                    InductionTemplateItem::create([
                        'section_id' => $newSection->id,
                        'title' => $item->title,
                        'content' => $item->content,
                        'response_type' => $item->response_type,
                        'response_options' => $item->response_options,
                        'order' => $item->order,
                        'is_required' => $item->is_required,
                        'requires_signature' => $item->requires_signature,
                        'requires_document' => $item->requires_document,
                        'document_instructions' => $item->document_instructions,
                    ]);
                }
            }

            return $newTemplate->load(['sections.items']);
        });
    }

    /**
     * Crear nueva versión de plantilla
     */
    public function createNewVersion(int $templateId, int $companyId, int $createdBy): InductionTemplate
    {
        return DB::transaction(function () use ($templateId, $companyId, $createdBy) {
            $originalTemplate = InductionTemplate::where('id', $templateId)
                ->where('company_id', $companyId)
                ->with(['sections.items'])
                ->firstOrFail();

            // Desactivar versión anterior
            $originalTemplate->update(['is_active' => false]);

            // Crear nueva versión
            $newVersion = $this->duplicateTemplate(
                $templateId,
                $companyId,
                $createdBy,
                $originalTemplate->name
            );

            $newVersion->update([
                'version' => $originalTemplate->version + 1,
            ]);

            return $newVersion;
        });
    }

    /**
     * Crear sección con sus items
     */
    protected function createSection(int $templateId, array $sectionData): InductionTemplateSection
    {
        $section = InductionTemplateSection::create([
            'template_id' => $templateId,
            'title' => $sectionData['title'],
            'description' => $sectionData['description'] ?? null,
            'order' => $sectionData['order'],
            'is_required' => $sectionData['is_required'] ?? true,
        ]);

        // Crear items de la sección
        if (isset($sectionData['items']) && is_array($sectionData['items'])) {
            foreach ($sectionData['items'] as $itemData) {
                InductionTemplateItem::create([
                    'section_id' => $section->id,
                    'title' => $itemData['title'],
                    'content' => $itemData['content'] ?? null,
                    'response_type' => $itemData['response_type'],
                    'response_options' => $itemData['response_options'] ?? null,
                    'order' => $itemData['order'],
                    'is_required' => $itemData['is_required'] ?? true,
                    'requires_signature' => $itemData['requires_signature'] ?? false,
                    'requires_document' => $itemData['requires_document'] ?? false,
                    'document_instructions' => $itemData['document_instructions'] ?? null,
                ]);
            }
        }

        return $section->load('items');
    }

    /**
     * Obtener estadísticas de uso de plantilla
     */
    public function getTemplateStats(int $templateId, int $companyId): array
    {
        $template = InductionTemplate::where('id', $templateId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $processes = InductionProcess::where('template_id', $template->id);

        return [
            'total_processes' => (clone $processes)->count(),
            'completed_processes' => (clone $processes)->where('status', 'completed')->count(),
            'in_progress_processes' => (clone $processes)->whereIn('status', ['sent', 'in_progress'])->count(),
            'cancelled_processes' => (clone $processes)->where('status', 'cancelled')->count(),
            'expired_processes' => (clone $processes)->where('status', 'expired')->count(),
            'average_completion_days' => (clone $processes)
                ->where('status', 'completed')
                ->whereNotNull('completion_date')
                ->selectRaw('AVG(DATEDIFF(completion_date, assigned_date)) as avg_days')
                ->value('avg_days'),
        ];
    }
}
