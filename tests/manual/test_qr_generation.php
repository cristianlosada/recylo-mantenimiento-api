#!/usr/bin/env php
<?php

/**
 * Script de Testing para Funcionalidad de Códigos QR
 * 
 * Este script permite probar la funcionalidad de generación de códigos QR
 * sin necesidad de hacer requests HTTP al API.
 * 
 * Uso:
 *   docker-compose exec app php tests/manual/test_qr_generation.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "═══════════════════════════════════════════════════════════\n";
echo "  TEST DE GENERACIÓN DE CÓDIGOS QR - Módulo de Activos\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Verificar que Firebase está configurado
echo "1. Verificando configuración de Firebase...\n";
$firebaseBucket = config('services.firebase.storage_bucket');
$firebaseApiKey = config('services.firebase.api_key');

if (empty($firebaseBucket) || empty($firebaseApiKey)) {
    echo "   ❌ ERROR: Firebase no está configurado correctamente\n";
    echo "   Por favor, configura las siguientes variables en .env:\n";
    echo "   - FIREBASE_STORAGE_BUCKET\n";
    echo "   - FIREBASE_API_KEY\n\n";
    exit(1);
}

echo "   ✅ Firebase configurado correctamente\n";
echo "   - Bucket: {$firebaseBucket}\n\n";

// Instanciar el servicio
echo "2. Instanciando QRCodeService...\n";
try {
    $qrService = app(App\Services\QRCodeService::class);
    echo "   ✅ Servicio instanciado correctamente\n\n";
} catch (Exception $e) {
    echo "   ❌ ERROR al instanciar servicio: {$e->getMessage()}\n";
    exit(1);
}

// Generar un código QR de prueba
echo "3. Generando código QR de prueba...\n";
$testData = [
    'asset_id' => 9999,
    'code' => 'TEST-QR-001',
    'name' => 'Activo de Prueba QR',
    'company_id' => 1,
    'url' => config('app.frontend_url', config('app.url')) . '/assets/9999'
];

echo "   Datos del QR:\n";
echo "   - Asset ID: {$testData['asset_id']}\n";
echo "   - Código: {$testData['code']}\n";
echo "   - Nombre: {$testData['name']}\n";
echo "   - Company ID: {$testData['company_id']}\n";
echo "   - URL Frontend: {$testData['url']}\n\n";

try {
    $qrUrl = $qrService->generateAndUpload($testData, 'test_asset_9999_TEST-QR-001');
    
    if ($qrUrl) {
        echo "   ✅ Código QR generado exitosamente!\n";
        echo "   📍 URL del QR: {$qrUrl}\n\n";
    } else {
        echo "   ❌ ERROR: No se pudo generar el código QR\n";
        echo "   Revisa los logs en storage/logs/laravel.log\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ EXCEPCIÓN: {$e->getMessage()}\n";
    echo "   {$e->getTraceAsString()}\n\n";
    exit(1);
}

// Test de regeneración
echo "4. Probando regeneración de código QR...\n";
$newData = [
    'asset_id' => 9999,
    'code' => 'TEST-QR-002',
    'name' => 'Activo de Prueba QR Actualizado',
    'company_id' => 1,
    'url' => config('app.frontend_url', config('app.url')) . '/assets/9999'
];

try {
    $newQrUrl = $qrService->regenerate($qrUrl, $newData, 'test_asset_9999_TEST-QR-002');
    
    if ($newQrUrl) {
        echo "   ✅ Código QR regenerado exitosamente!\n";
        echo "   📍 Nueva URL del QR: {$newQrUrl}\n\n";
    } else {
        echo "   ❌ ERROR: No se pudo regenerar el código QR\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ EXCEPCIÓN: {$e->getMessage()}\n\n";
    exit(1);
}

// Test de eliminación
echo "5. Probando eliminación de código QR...\n";
try {
    $deleted = $qrService->delete($newQrUrl);
    
    if ($deleted) {
        echo "   ✅ Código QR eliminado exitosamente!\n\n";
    } else {
        echo "   ⚠️  ADVERTENCIA: No se pudo eliminar el código QR\n";
        echo "   (Puede ser normal si el archivo ya no existe)\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ EXCEPCIÓN: {$e->getMessage()}\n\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  PRUEBAS COMPLETADAS\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "\nPróximos pasos:\n";
echo "1. Verifica en Firebase Storage que los archivos fueron creados/eliminados\n";
echo "2. Descarga un código QR y escanéalo con tu dispositivo móvil\n";
echo "3. Verifica que los datos decodificados coinciden con los esperados\n\n";

echo "Para probar con un activo real:\n";
echo "  docker-compose exec app php artisan tinker\n";
echo "  >>> \$asset = App\\Models\\Asset::first();\n";
echo "  >>> \$service = app(App\\Services\\QRCodeService::class);\n";
echo "  >>> \$service->generateAndUpload(['asset_id' => \$asset->id, ...], 'asset_' . \$asset->id);\n\n";

exit(0);
