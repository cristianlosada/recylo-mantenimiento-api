<?php

namespace App\Observers;

use App\Models\Asset;

class AssetObserver
{
    /**
     * Handle the Asset "saving" event.
     * Calcula y actualiza location_path antes de guardar
     */
    public function saving(Asset $asset): void
    {
        // Solo calcular si el activo tiene nombre (requerido para la ruta)
        if ($asset->name) {
            $asset->location_path = $this->calculateFullPath($asset);
        }
    }

    /**
     * Handle the Asset "saved" event.
     * Actualiza recursivamente los hijos si cambió el nombre o el padre
     */
    public function saved(Asset $asset): void
    {
        // Verificar si cambió el nombre o el parent_id
        if ($asset->wasChanged(['name', 'parent_id', 'company_site_id', 'production_line_id', 'category_id'])) {
            $this->updateChildrenPaths($asset);
        }
    }

    /**
     * Calcula la ruta completa jerárquica del activo
     * Recorre recursivamente los padres hasta llegar a la raíz
     */
    private function calculateFullPath(Asset $asset): string
    {
        // ── 1. Subir la jerarquía de activos hasta el activo raíz ──────────────
        $assetPath = [];
        $current   = $asset;
        $assetPath[] = $current->name;

        $maxDepth = 10;
        $depth    = 0;

        while ($current->parent_id && $depth < $maxDepth) {
            if (!$current->relationLoaded('parent')) {
                $current->load('parent');
            }
            $parent = $current->parent;
            if (!$parent) break;
            array_unshift($assetPath, $parent->name);
            $current = $parent;
            $depth++;
        }

        // $current es ahora el activo raíz; cargar sus relaciones de contexto
        if (!$current->relationLoaded('companySite')) {
            $current->load('companySite', 'productionLine', 'category');
        }

        // ── 2. Construir prefijo: Sede / Línea / Categoría ─────────────────────
        $prefix = [];

        if ($current->companySite?->name) {
            $prefix[] = $current->companySite->name;
        }

        if ($current->productionLine?->name) {
            $prefix[] = $current->productionLine->name;
        }

        if ($current->category?->name) {
            $prefix[] = $current->category->name;
        }

        // ── 3. Unir prefijo + jerarquía de activos ─────────────────────────────
        $fullPath = array_merge($prefix, $assetPath);

        return '/' . implode('/', $fullPath);
    }

    /**
     * Actualiza recursivamente los location_path de todos los hijos
     */
    private function updateChildrenPaths(Asset $asset): void
    {
        // Obtener todos los hijos directos
        $children = Asset::where('parent_id', $asset->id)->get();

        foreach ($children as $child) {
            // Recalcular el path del hijo
            $newPath = $this->calculateFullPath($child);

            // Actualizar sin disparar eventos (evitar recursión infinita)
            $child->timestamps = false; // Preservar timestamps originales
            $child->location_path = $newPath;
            $child->saveQuietly();
            $child->timestamps = true;

            // Actualizar recursivamente los hijos del hijo
            $this->updateChildrenPaths($child);
        }
    }
}
