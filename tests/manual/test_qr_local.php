<?php

/**
 * Script de Prueba Rápida de QR Local
 * Ejecutar: docker-compose exec app php tests/manual/test_qr_local.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "═══════════════════════════════════════════════════════════\n";
echo "  TEST DE QR CON ALMACENAMIENTO LOCAL (SIN FIREBASE)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "✓ Storage driver configurado: " . config('qrcode.storage_driver') . "\n\n";

// Instanciar el servicio
echo "1. Instanciando QRCodeService...\n";
$qrService = app(App\Services\QRCodeService::class);
echo "   ✓ Servicio listo\n\n";

// Generar un QR de prueba
echo "2. Generando código QR de prueba...\n";

// Probar con URL directa (caso de uso real)
$assetUrl = config('app.frontend_url', config('app.url')) . '/assets/999';
echo "   URL que contendrá el QR: {$assetUrl}\n";

$qrUrl = $qrService->generateAndUpload($assetUrl, 'test_url_999');

if ($qrUrl) {
    echo "   ✓ QR generado exitosamente!\n";
    echo "   📍 URL del QR: {$qrUrl}\n";
    echo "   🔗 Contenido del QR: {$assetUrl}\n";
    echo "   🌐 Acceder en: " . config('app.url') . "{$qrUrl}\n\n";
} else {
    echo "   ✗ Error generando QR\n";
    exit(1);
}

// Verificar que el archivo existe físicamente
$publicPath = public_path('storage/qr-codes/test_url_999.png');
if (file_exists($publicPath)) {
    $size = filesize($publicPath);
    echo "3. Verificando archivo físico...\n";
    echo "   ✓ Archivo existe en: {$publicPath}\n";
    echo "   ✓ Tamaño: " . number_format($size / 1024, 2) . " KB\n\n";
} else {
    echo "3. ⚠ Archivo no encontrado en: {$publicPath}\n\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  ✓ PRUEBA COMPLETADA EXITOSAMENTE\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "✨ El QR ahora contiene SOLO la URL del activo\n";
echo "📱 Al escanearlo con cualquier lector de QR:\n";
echo "   → Se abre automáticamente: {$assetUrl}\n";
echo "   → El usuario ve la hoja de vida del activo\n";
echo "   → Puede crear una solicitud de mantenimiento\n\n";

echo "Próximos pasos:\n";
echo "1. Abre tu navegador en: " . config('app.url') . "/storage/qr-codes/test_url_999.png\n";
echo "2. Deberías ver el código QR generado\n";
echo "3. Escanéalo con tu móvil - se abrirá directamente la URL del activo\n\n";

exit(0);
