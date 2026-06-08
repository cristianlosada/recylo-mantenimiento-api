<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FirebaseStorageService
{
    private ?string $bucket;
    private ?string $apiKey;
    private ?string $baseUrl = null;
    private bool $isConfigured;

    public function __construct()
    {
        $this->bucket = config('services.firebase.storage_bucket');
        $this->apiKey = config('services.firebase.api_key');
        $this->isConfigured = !empty($this->bucket) && !empty($this->apiKey);
        
        if ($this->isConfigured) {
            $this->baseUrl = "https://firebasestorage.googleapis.com/v0/b/{$this->bucket}/o";
        }
    }

    /**
     * Verifica si Firebase está configurado
     */
    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Subir un archivo a Firebase Storage
     * 
     * @param mixed $file UploadedFile o contenido binario del archivo
     * @param string $pathOrFolder Path completo (con extension) o carpeta
     * @param string|null $mimeType Tipo MIME (requerido si $file es binario)
     * @return string URL pública del archivo
     * @throws \Exception Si Firebase no está configurado
     */
    public function uploadFile($file, string $pathOrFolder = 'assets', ?string $mimeType = null): string
    {
        if (!$this->isConfigured) {
            throw new \Exception('Firebase Storage no está configurado. Configura FIREBASE_STORAGE_BUCKET y FIREBASE_API_KEY en .env');
        }
        
        // Detectar si es UploadedFile o contenido binario
        if (is_string($file)) {
            // Es contenido binario (QR code, etc)
            $fileContent = $file;
            $filePath = $pathOrFolder; // Ya viene con el path completo
            $contentType = $mimeType ?? 'application/octet-stream';
        } else {
            // Es UploadedFile
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $filePath = "{$pathOrFolder}/{$filename}";
            $fileContent = file_get_contents($file->getRealPath());
            $contentType = $file->getMimeType();
        }
        
        // Encodear el path para la URL
        $encodedPath = urlencode($filePath);
        
        // Subir archivo a Firebase Storage
        $response = Http::withHeaders([
            'Content-Type' => $contentType,
        ])->post("{$this->baseUrl}/{$encodedPath}?uploadType=media&name={$encodedPath}", $fileContent);

        if (!$response->successful()) {
            throw new \Exception('Error al subir archivo a Firebase: ' . $response->body());
        }

        // Retornar URL pública
        return $this->getPublicUrl($filePath);
    }

    /**
     * Eliminar un archivo de Firebase Storage
     * 
     * @param string $fileUrl URL del archivo a eliminar
     * @return bool
     */
    public function deleteFile(string $fileUrl): bool
    {
        try {
            // Extraer el path del archivo desde la URL
            $filePath = $this->extractPathFromUrl($fileUrl);
            
            if (!$filePath) {
                return false;
            }

            $encodedPath = urlencode($filePath);
            
            // Eliminar archivo de Firebase Storage
            $response = Http::delete("{$this->baseUrl}/{$encodedPath}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error al eliminar archivo de Firebase: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener URL pública del archivo
     * 
     * @param string $filePath
     * @return string
     */
    private function getPublicUrl(string $filePath): string
    {
        $encodedPath = urlencode($filePath);
        
        // URL pública con token (sin autenticación)
        // Nota: Firebase Storage requiere configurar reglas de acceso público
        return "https://firebasestorage.googleapis.com/v0/b/{$this->bucket}/o/{$encodedPath}?alt=media";
    }

    /**
     * Extraer el path del archivo desde una URL de Firebase
     * 
     * @param string $url
     * @return string|null
     */
    private function extractPathFromUrl(string $url): ?string
    {
        // Patrón: https://firebasestorage.googleapis.com/v0/b/{bucket}/o/{path}?alt=media
        if (preg_match('/\/o\/([^?]+)/', $url, $matches)) {
            return urldecode($matches[1]);
        }
        
        return null;
    }
}
