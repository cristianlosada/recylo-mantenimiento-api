<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QR Code Storage Driver
    |--------------------------------------------------------------------------
    |
    | Este valor determina dónde se almacenarán los códigos QR generados.
    |
    | Opciones:
    | - 'local': Almacenamiento local en storage/app/public/qr-codes
    |            No requiere configuración adicional. SIEMPRE FUNCIONA.
    |
    | - 'firebase': Firebase Storage (requiere credenciales configuradas)
    |               Si Firebase falla, automáticamente usa 'local' como fallback
    |
    | - 's3': Amazon S3 (próximamente)
    |
    */

    'storage_driver' => env('QRCODE_STORAGE_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Configuración de QR Code
    |--------------------------------------------------------------------------
    */

    'size' => env('QRCODE_SIZE', 300), // Tamaño en píxeles
    'margin' => env('QRCODE_MARGIN', 2), // Margen en píxeles
    'error_correction' => env('QRCODE_ERROR_CORRECTION', 'H'), // L, M, Q, H

];
