<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    /**
     * GET /api/app/version
     *
     * Endpoint público — no requiere autenticación.
     *
     * Lee la configuración de versión desde SystemSetting (BD con cache 1h).
     * Si una clave no existe en BD, cae al valor de config/app_version.php.
     *
     * Para actualizar sin redeploy ni reiniciar el servidor:
     *   SystemSetting::set('mobile_version_enforce', true, ..., 'boolean');
     *   SystemSetting::set('mobile_version_latest', '1.1.0', ..., 'string');
     *   ...etc
     *
     * Campos de respuesta:
     *   enforce         bool    Si true, bloquear app hasta que actualice
     *   minimum_version string  Versión mínima aceptada (semver)
     *   latest_version  string  Última versión publicada
     *   download_url    string  URL directa del APK (Google Drive u otro)
     *   update_message  string  Mensaje personalizado al usuario
     *   release_notes   string  Notas de la última versión (opcional)
     */
    public function check(): JsonResponse
    {
        return ApiResponse::success([
            'enforce' => (bool) SystemSetting::get(
                'mobile_version_enforce',
                config('app_version.enforce', false)
            ),
            'minimum_version' => SystemSetting::get(
                'mobile_version_minimum',
                config('app_version.minimum_version', '1.0.0')
            ),
            'latest_version' => SystemSetting::get(
                'mobile_version_latest',
                config('app_version.latest_version', '1.0.0')
            ),
            'download_url' => SystemSetting::get(
                'mobile_version_download_url',
                config('app_version.download_url', '')
            ),
            'update_message' => SystemSetting::get(
                'mobile_version_update_message',
                config('app_version.update_message', '')
            ),
            'release_notes' => SystemSetting::get(
                'mobile_version_release_notes',
                config('app_version.release_notes', '')
            ),
            'distribution_channel' => SystemSetting::get(
                'mobile_distribution_channel',
                'drive'  // 'drive' | 'playstore'
            ),
        ], 'Información de versión de la aplicación');
    }
}
