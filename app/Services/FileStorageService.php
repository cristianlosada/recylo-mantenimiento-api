<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

/**
 * Servicio unificado de almacenamiento de archivos
 * Soporta almacenamiento local y Firebase con fallback automático
 */
class FileStorageService
{
    protected $firebaseStorage;
    protected $storageDriver;

    public function __construct(FirebaseStorageService $firebaseStorage)
    {
        $this->firebaseStorage = $firebaseStorage;
        // 'local' o 'firebase' según configuración
        $this->storageDriver = config('filesystems.default_upload_driver', 'local');
    }

    /**
     * Subir archivo al almacenamiento configurado
     * NUNCA falla - usa fallback a local si Firebase falla
     *
     * @param UploadedFile $file Archivo a subir
     * @param string $folder Carpeta destino (ej: 'assets', 'documents')
     * @return string|null URL pública del archivo
     */
    public function uploadFile(UploadedFile $file, string $folder = 'uploads'): ?string
    {
        try {
            // Intentar con el driver configurado
            if ($this->storageDriver === 'firebase' && $this->firebaseStorage->isConfigured()) {
                $url = $this->uploadToFirebase($file, $folder);
                if ($url) {
                    return $url;
                }
                Log::info('Firebase no disponible, usando almacenamiento local como fallback');
            }

            // Usar almacenamiento local (siempre funciona)
            return $this->uploadToLocal($file, $folder);

        } catch (\Exception $e) {
            Log::error('Error subiendo archivo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Subir archivo a Firebase Storage
     */
    protected function uploadToFirebase(UploadedFile $file, string $folder): ?string
    {
        try {
            return $this->firebaseStorage->uploadFile($file, $folder);
        } catch (\Exception $e) {
            Log::warning('Firebase Storage no disponible: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Subir archivo a almacenamiento local
     */
    protected function uploadToLocal(UploadedFile $file, string $folder): ?string
    {
        try {
            // Generar nombre único
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = "{$folder}/{$filename}";
            
            // Guardar en storage/app/public/{folder}
            Storage::disk('public')->putFileAs($folder, $file, $filename);
            
            // Retornar URL pública
            return asset('storage/' . $path);
        } catch (\Exception $e) {
            Log::error('Error guardando archivo en almacenamiento local: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Eliminar archivo del almacenamiento (detecta automáticamente el tipo)
     *
     * @param string|null $url URL del archivo
     * @return bool
     */
    public function deleteFile(?string $url): bool
    {
        if (!$url) {
            return true;
        }

        try {
            // Detectar si es Firebase o local
            if (str_contains($url, 'firebasestorage.googleapis.com')) {
                return $this->firebaseStorage->deleteFile($url);
            } else {
                // Es almacenamiento local
                $path = str_replace(asset('storage/'), '', $url);
                return Storage::disk('public')->delete($path);
            }
        } catch (\Exception $e) {
            Log::error('Error eliminando archivo: ' . $e->getMessage());
            return false;
        }
    }
}
