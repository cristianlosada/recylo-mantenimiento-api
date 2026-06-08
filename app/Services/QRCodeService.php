<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class QRCodeService
{
    protected $storageDriver;

    public function __construct()
    {
        // Solo usar almacenamiento local
        $this->storageDriver = 'local';
    }

    /**
     * Generar código QR y subirlo al almacenamiento configurado
     * NUNCA falla - siempre intenta guardar localmente si Firebase falla
     *
     * @param string|array $data URL o datos a codificar en el QR
     * @param string $filename Nombre del archivo (sin extensión)
     * @return string|null URL del QR o null si todo falla
     */
    public function generateAndUpload($data, string $filename): ?string
    {
        try {
            // Si data es array, tomar la URL; si es string, usarla directamente
            $qrContent = is_array($data) ? ($data['url'] ?? json_encode($data)) : $data;
            
            // Generar QR code directamente (BaconQr no necesita backend específico)
            // Retorna string binario PNG
            $qrCode = QrCode::format('png')
                ->size(300)
                ->errorCorrection('H')
                ->margin(2)
                ->generate($qrContent);

            $path = "qr-codes/{$filename}.png";

            // Usar almacenamiento local siempre
            return $this->uploadToLocal($qrCode, $path);

        } catch (\Exception $e) {
            Log::error('Error generando QR code: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sube el QR a almacenamiento local
     */
    protected function uploadToLocal($qrCode, string $path): ?string
    {
        try {
            // Guardar en storage/app/public/qr-codes
            Storage::disk('public')->put($path, $qrCode);

            // Usar la URL del disco 'public' (STORAGE_URL en .env, o APP_URL/storage)
            // NO usar asset() porque ese helper depende de APP_URL, que puede apuntar al frontend
            return Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::error('Error guardando QR en almacenamiento local: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Eliminar código QR del almacenamiento local
     *
     * @param string $url URL del QR
     * @return bool
     */
    public function delete(string $url): bool
    {
        try {
            // Es almacenamiento local
            // Extraer el path relativo desde la URL (ej: /storage/qr-codes/filename.png -> qr-codes/filename.png)
            $path = str_replace(asset('storage/'), '', $url);
            return Storage::disk('public')->delete($path);
        } catch (\Exception $e) {
            Log::error('Error eliminando QR code: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Regenerar código QR (elimina el anterior y crea uno nuevo)
     *
     * @param string|null $oldUrl URL del QR anterior
     * @param array $data Nuevos datos
     * @param string $filename Nombre del archivo
     * @return string|null Nueva URL del QR
     */
    public function regenerate(?string $oldUrl, array $data, string $filename): ?string
    {
        // Eliminar QR anterior si existe
        if ($oldUrl) {
            $this->delete($oldUrl);
        }

        // Generar nuevo QR
        return $this->generateAndUpload($data, $filename);
    }
}
