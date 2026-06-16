<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanySiteController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\RoleDelegationController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\WorkRequestController;
use App\Http\Controllers\Api\WorkOrderController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\AssetMeterController;
use App\Http\Controllers\Api\MaintenancePlanController;
use App\Http\Controllers\Api\ProductionLineController;
use App\Http\Controllers\Api\MaintenanceTypeController;
use App\Http\Controllers\Api\AssetVendorController;
use App\Http\Controllers\Api\AssetSystemController;
use App\Http\Controllers\Api\InspectionShiftController;
use App\Http\Controllers\Api\InspectionTemplateController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\PublicInspectionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ========================================
// VERSIÓN DE LA APP — público, sin auth
// ========================================
Route::get('/app/version', [AppVersionController::class, 'check'])
    ->middleware('throttle:60,1');

// Rutas públicas de autenticación con rate limiting
Route::prefix('auth')->middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/device-token', [AuthController::class, 'deviceToken']);
});

// ========================================
// RUTAS PÚBLICAS - SOLICITUDES DE TRABAJO (QR CODE)
// ========================================
Route::prefix('public')->group(function () {
    // Vista HTML pública del activo (desde QR escanedado)
    Route::get('/asset-view/{assetCode}', [AssetController::class, 'publicAssetView']);
    
    // Listar activos públicamente (para búsqueda cuando QR no es legible)
    Route::get('/assets', [AssetController::class, 'publicAssetsList'])
        ->middleware('throttle:60,1'); // Máximo 60 solicitudes por minuto
    
    // Obtener información del activo para formulario (API JSON)
    Route::get('/asset/{assetCode}', [WorkRequestController::class, 'getAssetInfo']);
    
    // Crear solicitud desde formulario público
    Route::post('/work-requests', [WorkRequestController::class, 'storePublic'])
        ->middleware('throttle:60,1'); // 60 por minuto por IP

    // PDF público de solicitud (acceso por código, sin autenticación)
    Route::get('/work-requests/{code}/pdf', [WorkRequestController::class, 'generatePublicPdf'])
        ->middleware('throttle:30,1');

    // Usuarios de una empresa para el selector del formulario público (HU-S1)
    Route::get('/users', [WorkRequestController::class, 'getPublicUsers'])
        ->middleware('throttle:60,1');

    // Activos filtrados por empresa / usuario / sede / línea (HU-S1)
    Route::get('/assets-filtered', [WorkRequestController::class, 'publicAssetsFiltered'])
        ->middleware('throttle:60,1');

    // Inspecciones preoperacionales públicas (sin autenticación)
    Route::get('/inspection-shifts',             [InspectionShiftController::class, 'index'])
        ->middleware('throttle:60,1');
    Route::get('/inspection-assets',             [PublicInspectionController::class, 'assets'])
        ->middleware('throttle:60,1');
    Route::get('/inspection-templates',            [PublicInspectionController::class, 'templates'])
        ->middleware('throttle:60,1');
    Route::get('/inspection-operators',          [PublicInspectionController::class, 'operators'])
        ->middleware('throttle:60,1');
    Route::post('/inspections',                  [PublicInspectionController::class, 'store'])
        ->middleware('throttle:30,1');

    // Panel TV de autogestión — OTs pendientes sin asignar
    Route::get('/work-orders/pending-unassigned', [WorkOrderController::class, 'pendingUnassigned'])
        ->middleware('throttle:120,1');
    Route::get('/work-orders/recently-assigned', [WorkOrderController::class, 'recentlyAssigned'])
        ->middleware('throttle:120,1');
    Route::post('/work-orders/{id}/self-assign', [WorkOrderController::class, 'selfAssign'])
        ->middleware('throttle:30,1');
});

// Rutas protegidas por autenticación
Route::middleware('auth:sanctum')->group(function () {
    
    // Rutas de autenticación protegidas
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        
        // Refresh token con rate limiting moderado
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:10,1');

        // Refresh token para dispositivos móviles (devuelve token en body)
        Route::post('/device-refresh', [AuthController::class, 'deviceRefresh'])
            ->middleware('throttle:10,1');
        
        // Gestión de sesiones
        Route::get('/sessions', [AuthController::class, 'activeSessions']);
        Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeSession']);
    });

    // Ruta legacy para compatibilidad
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rutas de gestión de usuarios
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/by-company/{companyId}', [UserController::class, 'getUsersByCompany']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        
        // Rutas adicionales para gestión de roles y empresas
        Route::post('/{id}/assign-role', [UserController::class, 'assignRole']);
        Route::delete('/{id}/remove-role', [UserController::class, 'removeRole']);
        Route::put('/{id}/change-password', [UserController::class, 'changePassword']);
        Route::post('/{id}/assign-company', [UserController::class, 'assignToCompany']);
        Route::post('/{id}/remove-company', [UserController::class, 'removeFromCompany']);
        Route::post('/bulk-import', [UserController::class, 'bulkImport']);
    });

    // Cargos (job positions) por empresa
    Route::prefix('job-positions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\JobPositionController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\JobPositionController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\JobPositionController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\JobPositionController::class, 'destroy']);
    });

    // Rutas de gestión de empresas
    Route::prefix('companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index']);
        Route::get('/{id}', [CompanyController::class, 'show']);
        Route::post('/', [CompanyController::class, 'store']);
        Route::put('/{id}', [CompanyController::class, 'update']);
        Route::delete('/{id}', [CompanyController::class, 'destroy']);
        
        // Rutas adicionales para gestión de empresa
        Route::put('/{id}/subscription', [CompanyController::class, 'updateSubscription']);
        Route::put('/{id}/modules', [CompanyController::class, 'updateModules']);
        
        // Rutas para gestión de sedes de la empresa
        Route::get('/{companyId}/sites', [CompanySiteController::class, 'index']);
        Route::get('/{companyId}/sites/{siteId}', [CompanySiteController::class, 'show']);
        Route::post('/{companyId}/sites', [CompanySiteController::class, 'store']);
        Route::put('/{companyId}/sites/{siteId}', [CompanySiteController::class, 'update']);
        Route::delete('/{companyId}/sites/{siteId}', [CompanySiteController::class, 'destroy']);
        
        // Acciones específicas para sedes
        Route::post('/{companyId}/sites/{siteId}/set-headquarters', [CompanySiteController::class, 'setHeadquarters']);
        Route::post('/{companyId}/sites/{siteId}/toggle-active', [CompanySiteController::class, 'toggleActive']);
        
        // Rutas para gestión de activos de la empresa
        Route::get('/{companyId}/assets', [AssetController::class, 'index']);
        Route::get('/{companyId}/assets/export-csv',   [AssetController::class, 'exportCsv']);
        Route::get('/{companyId}/assets/export-excel', [AssetController::class, 'exportExcel']);
        Route::get('/{companyId}/assets/search', [AssetController::class, 'search']);
        Route::post('/{companyId}/assets/bulk-import', [AssetController::class, 'bulkImport']);
        Route::get('/{companyId}/assets/{assetId}', [AssetController::class, 'show']);
        Route::post('/{companyId}/assets', [AssetController::class, 'store']);
        Route::put('/{companyId}/assets/{assetId}', [AssetController::class, 'update']);
        Route::delete('/{companyId}/assets/{assetId}', [AssetController::class, 'destroy']);
        
        // Gestión de especificaciones técnicas
        Route::post('/{companyId}/assets/{assetId}/specifications', [AssetController::class, 'addSpecification']);
        Route::put('/{companyId}/assets/{assetId}/specifications/{specId}', [AssetController::class, 'updateSpecification']);
        Route::delete('/{companyId}/assets/{assetId}/specifications/{specId}', [AssetController::class, 'deleteSpecification']);
        
        // Gestión de usuarios asignados
        Route::get('/{companyId}/assets/{assetId}/users', [AssetController::class, 'getAssignedUsers']);
        Route::post('/{companyId}/assets/{assetId}/users', [AssetController::class, 'assignUser']);
        Route::delete('/{companyId}/assets/{assetId}/users/{userId}', [AssetController::class, 'removeUser']);
        
        // Estadísticas y reportes
        Route::get('/{companyId}/assets/stats', [AssetController::class, 'stats']);
        Route::get('/{companyId}/assets/by-category', [AssetController::class, 'getAssetsByCategory']);
        Route::get('/{companyId}/assets/by-status', [AssetController::class, 'getAssetsByStatus']);
        Route::get('/{companyId}/assets/by-site', [AssetController::class, 'getAssetsBySite']);
        
        // Acciones especiales
        Route::post('/{companyId}/assets/{assetId}/toggle-active', [AssetController::class, 'toggleActive']);
        Route::post('/{companyId}/assets/{assetId}/move', [AssetController::class, 'moveAsset']);
        Route::post('/{companyId}/assets/{assetId}/duplicate', [AssetController::class, 'duplicateAsset']);
        Route::post('/{companyId}/assets/{assetId}/generate-qr', [AssetController::class, 'generateQR']);
        Route::get('/{companyId}/assets/{assetId}/export-pdf', [AssetController::class, 'exportPdf']);
        
        // PDFs y documentos
        Route::get('/{companyId}/assets/{assetId}/pdf/profile', [AssetController::class, 'generateAssetProfilePDF']);
        Route::get('/{companyId}/assets/{assetId}/pdf/qr-label', [AssetController::class, 'generateQRLabelPDF']);
        
        // Módulos de detalle del activo
        // Notas
        Route::get('/{companyId}/assets/{assetId}/notes', [AssetController::class, 'getNotes']);
        Route::post('/{companyId}/assets/{assetId}/notes', [AssetController::class, 'createNote']);
        Route::delete('/{companyId}/assets/{assetId}/notes/{noteId}', [AssetController::class, 'deleteNote']);
        
        // Notificaciones
        Route::get('/{companyId}/assets/{assetId}/notifications', [AssetController::class, 'getNotifications']);
        Route::post('/{companyId}/assets/{assetId}/notifications', [AssetController::class, 'createNotification']);
        Route::put('/{companyId}/assets/{assetId}/notifications/{notificationId}', [AssetController::class, 'updateNotification']);
        Route::delete('/{companyId}/assets/{assetId}/notifications/{notificationId}', [AssetController::class, 'deleteNotification']);
        
        // Repuestos asociados (legacy)
        Route::get('/{companyId}/assets/{assetId}/spare-parts', [AssetController::class, 'getSpareParts']);
        Route::post('/{companyId}/assets/{assetId}/spare-parts', [AssetController::class, 'createSparePart']);
        Route::delete('/{companyId}/assets/{assetId}/spare-parts/{sparePartId}', [AssetController::class, 'deleteSparePart']);

        // Componentes del activo (nuevo módulo)
        Route::get('/{companyId}/assets/{assetId}/components',                                     [App\Http\Controllers\Api\AssetComponentController::class, 'index']);
        Route::post('/{companyId}/assets/{assetId}/components',                                    [App\Http\Controllers\Api\AssetComponentController::class, 'store']);
        Route::put('/{companyId}/assets/{assetId}/components/{id}',                               [App\Http\Controllers\Api\AssetComponentController::class, 'update']);
        Route::delete('/{companyId}/assets/{assetId}/components/{id}',                            [App\Http\Controllers\Api\AssetComponentController::class, 'destroy']);
        Route::post('/{companyId}/assets/{assetId}/components/{id}/consume',                      [App\Http\Controllers\Api\AssetComponentController::class, 'consume']);
        Route::get('/{companyId}/assets/{assetId}/components/{id}/consumption-history',           [App\Http\Controllers\Api\AssetComponentController::class, 'consumptionHistory']);
        
        // Archivos adjuntos
        Route::get('/{companyId}/assets/{assetId}/attachments', [AssetController::class, 'getAttachments']);
        Route::post('/{companyId}/assets/{assetId}/attachments', [AssetController::class, 'uploadAttachment']);
        Route::get('/{companyId}/assets/{assetId}/attachments/{attachmentId}', [AssetController::class, 'downloadAttachment'])
            ->name('api.assets.attachments.download');
        Route::delete('/{companyId}/assets/{assetId}/attachments/{attachmentId}', [AssetController::class, 'deleteAttachment']);
        
        // Costos históricos
        Route::get('/{companyId}/assets/{assetId}/costs-history', [AssetController::class, 'getCostsHistory']);
        
        // Mediciones
        Route::get('/{companyId}/assets/{assetId}/measurements', [AssetController::class, 'getMeasurements']);
        Route::post('/{companyId}/assets/{assetId}/measurements', [AssetController::class, 'createMeasurement']);
        Route::delete('/{companyId}/assets/{assetId}/measurements/{measurementId}', [AssetController::class, 'deleteMeasurement']);
        
        // Historial de actividad
        Route::get('/{companyId}/assets/{assetId}/activity-log', [AssetController::class, 'getActivityLog']);
        
        // Órdenes de trabajo del activo
        Route::get('/{companyId}/assets/{assetId}/work-orders', [AssetController::class, 'getWorkOrders']);

        // Tipos de mantenimiento del activo (HU-A4)
        Route::get('/{companyId}/assets/{assetId}/maintenance-types', [MaintenanceTypeController::class, 'getAssetTypes']);
        Route::put('/{companyId}/assets/{assetId}/maintenance-types', [MaintenanceTypeController::class, 'syncAssetTypes']);
    });

    // Rutas de gestión de roles
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/with-permissions-by-module', [RoleController::class, 'getRolesWithPermissionsByModule']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store']);
        Route::put('/{id}', [RoleController::class, 'update']);
        Route::delete('/{id}', [RoleController::class, 'destroy']);
        
        // Rutas adicionales para gestión de roles
        Route::get('/by-company/{companyId}', [RoleController::class, 'getRolesByCompany']);
        Route::put('/{id}/permissions', [RoleController::class, 'updatePermissions']);
    });

    // Rutas de gestión de permisos
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        
        // Rutas específicas (deben ir ANTES de las rutas con parámetros dinámicos)
        Route::get('/by-modules', [PermissionController::class, 'getPermissionsByModules']);
        Route::get('/by-module/{moduleId}', [PermissionController::class, 'getPermissionsByModule']);
        Route::get('/by-role/{roleId}', [PermissionController::class, 'getPermissionsByRole']);
        Route::get('/modules-with-permissions', [PermissionController::class, 'getModulesWithPermissions']);
        
        // Rutas CRUD con parámetros dinámicos (deben ir DESPUÉS)
        Route::get('/{id}', [PermissionController::class, 'show']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::put('/{id}', [PermissionController::class, 'update']);
        Route::delete('/{id}', [PermissionController::class, 'destroy']);
    });

    // Rutas de gestión de módulos
    Route::prefix('modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index']);
        Route::get('/stats', [ModuleController::class, 'getModulesStats']);
        Route::get('/by-company/{companyId}', [ModuleController::class, 'getCompanyModules']);
        Route::get('/{id}', [ModuleController::class, 'show']);
        Route::post('/', [ModuleController::class, 'store']);
        Route::put('/{id}', [ModuleController::class, 'update']);
        Route::delete('/{id}', [ModuleController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [ModuleController::class, 'toggleStatus']);
    });

    // Rutas de gestión de delegaciones de roles
    Route::prefix('role-delegations')->group(function () {
        Route::get('/', [RoleDelegationController::class, 'index']);
        Route::get('/active', [RoleDelegationController::class, 'getActiveDelegations']);
        Route::get('/user/{userId}/delegated', [RoleDelegationController::class, 'getDelegatedRoles']);
        Route::get('/user/{userId}/created', [RoleDelegationController::class, 'getCreatedDelegations']);
        Route::post('/', [RoleDelegationController::class, 'store']);
        Route::put('/{id}', [RoleDelegationController::class, 'update']);
        Route::post('/{id}/revoke', [RoleDelegationController::class, 'revoke']);
        Route::delete('/{id}', [RoleDelegationController::class, 'destroy']);
    });

    // Rutas de datos de referencia
    Route::prefix('reference')->group(function () {
        // Datos básicos
        Route::get('/document-types', [ReferenceDataController::class, 'getDocumentTypes']);
        Route::get('/countries', [ReferenceDataController::class, 'getCountries']);
        Route::get('/currencies', [ReferenceDataController::class, 'getCurrencies']);
        
        // Opciones para usuarios
        Route::get('/gender-options', [ReferenceDataController::class, 'getGenderOptions']);
        Route::get('/user-status-options', [ReferenceDataController::class, 'getUserStatusOptions']);
        Route::get('/user-form-data', [ReferenceDataController::class, 'getUserFormData']);
        
        // Opciones para empresas
        Route::get('/company-form-data', [ReferenceDataController::class, 'getCompanyFormData']);
        Route::get('/company-modules', [ReferenceDataController::class, 'getCompanyModules']);
        Route::get('/plans', [ReferenceDataController::class, 'getPlans']);
        
        // Datos de ubicación
        Route::get('/location/{countryId}', [ReferenceDataController::class, 'getLocationData']);
        Route::get('/company-sizes', [ReferenceDataController::class, 'getCompanySizes']);
        Route::get('/economic-activities', [ReferenceDataController::class, 'getCompanyActivities']);
        Route::get('/employment-types', [ReferenceDataController::class, 'getEmploymentTypes']);
        Route::get('/job-categories', [ReferenceDataController::class, 'getJobCategories']);
        Route::get('/site-types', [ReferenceDataController::class, 'getSiteTypes']);
        
        // Datos de activos
        Route::get('/asset-categories', [ReferenceDataController::class, 'getAssetCategories']);
        Route::get('/asset-statuses', [ReferenceDataController::class, 'getAssetStatuses']);
        Route::get('/asset-priorities', [ReferenceDataController::class, 'getAssetPriorities']);
    });

    // ========================================
    // MÓDULO: INVENTARIO Y ALMACENES (INVENTORY & WAREHOUSES)
    // ========================================
    Route::prefix('warehouses')->group(function () {
        // Estadísticas
        Route::get('/stats', [App\Http\Controllers\Api\WarehouseController::class, 'stats']);
        
        // CRUD Almacenes
        Route::get('/', [App\Http\Controllers\Api\WarehouseController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\WarehouseController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\WarehouseController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\WarehouseController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\WarehouseController::class, 'destroy']);
        
        // Valorización del almacén
        Route::get('/{id}/valuation', [App\Http\Controllers\Api\WarehouseController::class, 'getValuation']);
    });
    // Categorías de materiales
    Route::prefix('material-categories')->group(function () {
        // Endpoints especiales (antes de {id})
        Route::get('/tree', [App\Http\Controllers\Api\MaterialCategoryController::class, 'tree']);
        Route::get('/stats', [App\Http\Controllers\Api\MaterialCategoryController::class, 'stats']);
        
        // CRUD Categorías
        Route::get('/', [App\Http\Controllers\Api\MaterialCategoryController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\MaterialCategoryController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\MaterialCategoryController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\MaterialCategoryController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\MaterialCategoryController::class, 'destroy']);
    });
    Route::prefix('materials')->group(function () {
        // Estadísticas (debe ir antes de {id})
        Route::get('/stats', [App\Http\Controllers\Api\MaterialController::class, 'stats']);
        
        // CRUD Materiales
        Route::get('/', [App\Http\Controllers\Api\MaterialController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\MaterialController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\MaterialController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\MaterialController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\MaterialController::class, 'destroy']);
        
        // Stock del material
        Route::get('/{id}/stock', [App\Http\Controllers\Api\MaterialController::class, 'getStock']);
        
        // Códigos de barras ✨ NUEVO
        Route::post('/{id}/barcode/generate', [App\Http\Controllers\Api\MaterialController::class, 'generateBarcode']);
        Route::get('/{id}/barcode', [App\Http\Controllers\Api\MaterialController::class, 'getBarcodeImage']);
    });

    // ── Tipos de componentes ──────────────────────────────────────────────────
    Route::prefix('component-types')->group(function () {
        Route::get('/',       [App\Http\Controllers\Api\ComponentTypeController::class, 'index']);
        Route::post('/',      [App\Http\Controllers\Api\ComponentTypeController::class, 'store']);
        Route::put('/{id}',   [App\Http\Controllers\Api\ComponentTypeController::class, 'update']);
        Route::delete('/{id}',[App\Http\Controllers\Api\ComponentTypeController::class, 'destroy']);
    });

    // ── Componentes (catálogo de inventario) ─────────────────────────────────
    Route::prefix('components')->group(function () {
        Route::get('/stats',              [App\Http\Controllers\Api\ComponentController::class, 'stats']);
        Route::get('/',                   [App\Http\Controllers\Api\ComponentController::class, 'index']);
        Route::post('/',                  [App\Http\Controllers\Api\ComponentController::class, 'store']);
        Route::get('/{id}',               [App\Http\Controllers\Api\ComponentController::class, 'show']);
        Route::put('/{id}',               [App\Http\Controllers\Api\ComponentController::class, 'update']);
        Route::delete('/{id}',            [App\Http\Controllers\Api\ComponentController::class, 'destroy']);
        Route::get('/{id}/stock',         [App\Http\Controllers\Api\ComponentController::class, 'getStock']);
        Route::post('/{id}/adjust-stock', [App\Http\Controllers\Api\ComponentController::class, 'adjustStock']);
        Route::post('/{id}/purchase',     [App\Http\Controllers\Api\ComponentController::class, 'registerPurchase']);
    });

    // Transacciones de inventario
    Route::prefix('inventory')->group(function () {
        // Listar y ver transacciones
        Route::get('/transactions', [InventoryTransactionController::class, 'index']);
        Route::get('/transactions/{id}', [InventoryTransactionController::class, 'show']);
        
        // Operaciones de stock
        Route::post('/adjust', [InventoryTransactionController::class, 'adjust']);
        Route::post('/transfer', [InventoryTransactionController::class, 'transfer']);
        Route::post('/purchase', [InventoryTransactionController::class, 'purchase']);
        Route::post('/damage', [InventoryTransactionController::class, 'damage']);
    });

    // ========================================
    // MÓDULO: SOLICITUDES DE TRABAJO (WORK REQUESTS)
    // ========================================
    Route::prefix('work-requests')->group(function () {
        // Tags
        Route::get('/tags', [WorkRequestController::class, 'getTags']);

        // Exportación Excel
        Route::get('/export', [WorkRequestController::class, 'export']);

        // Estadísticas (debe ir antes de {id} para evitar conflicto)
        Route::get('/stats/overview', [WorkRequestController::class, 'stats']);
        
        // Acciones masivas (deben ir antes de /{id} para evitar conflictos)
        Route::post('/bulk/approve', [WorkRequestController::class, 'bulkApprove']);
        Route::post('/bulk/delete', [WorkRequestController::class, 'bulkDestroy']);

        // Rutas CRUD principales
        Route::get('/', [WorkRequestController::class, 'index']);
        Route::post('/', [WorkRequestController::class, 'store']);
        Route::get('/{id}', [WorkRequestController::class, 'show']);
        Route::put('/{id}', [WorkRequestController::class, 'update']);
        Route::delete('/{id}', [WorkRequestController::class, 'destroy']);

        // Rutas de aprobación/rechazo
        Route::post('/{id}/approve', [WorkRequestController::class, 'approve']);
        Route::post('/{id}/reject', [WorkRequestController::class, 'reject']);
        
        // Comentarios
        Route::get('/{id}/comments', [WorkRequestController::class, 'getComments']);
        Route::post('/{id}/comments', [WorkRequestController::class, 'storeComment']);
        Route::put('/{id}/comments/{commentId}', [WorkRequestController::class, 'updateComment']);
        Route::delete('/{id}/comments/{commentId}', [WorkRequestController::class, 'destroyComment']);
        
        // Archivos adjuntos
        Route::post('/{id}/attachments', [WorkRequestController::class, 'uploadAttachments']);
        Route::delete('/{id}/attachments/{attachmentId}', [WorkRequestController::class, 'destroyAttachment']);
        
        // Watchers
        Route::get('/{id}/watchers', [WorkRequestController::class, 'getWatchers']);
        Route::post('/{id}/watchers', [WorkRequestController::class, 'addWatcher']);
        Route::delete('/{id}/watchers/{userId}', [WorkRequestController::class, 'removeWatcher']);
        
        // Solicitudes relacionadas
        Route::get('/{id}/related', [WorkRequestController::class, 'getRelated']);
        Route::post('/{id}/related', [WorkRequestController::class, 'linkRelated']);
        Route::delete('/{id}/related/{relatedId}', [WorkRequestController::class, 'unlinkRelated']);
        
        // Checklist
        Route::patch('/{id}/checklist/{itemId}', [WorkRequestController::class, 'toggleChecklistItem']);
        Route::put('/{id}/checklist/{itemId}/notes', [WorkRequestController::class, 'updateChecklistNotes']);

        // PDF
        Route::get('/{id}/pdf', [WorkRequestController::class, 'generatePdf']);
    });

    // ========================================
    // PLANTILLAS DE CHECKLIST (WORK REQUEST CHECKLIST TEMPLATES)
    // ========================================
    Route::prefix('work-request-checklist-templates')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\WorkRequestChecklistTemplateController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\WorkRequestChecklistTemplateController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\WorkRequestChecklistTemplateController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\WorkRequestChecklistTemplateController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\WorkRequestChecklistTemplateController::class, 'destroy']);
        
        // Acciones especiales
        Route::post('/{id}/toggle-active', [\App\Http\Controllers\Api\WorkRequestChecklistTemplateController::class, 'toggleActive']);
        Route::post('/{id}/duplicate', [\App\Http\Controllers\Api\WorkRequestChecklistTemplateController::class, 'duplicate']);
    });

    // ========================================
    // MÓDULO: ÓRDENES DE TRABAJO (WORK ORDERS)
    // ========================================
    
    // Vista de almacén - Materiales de todas las OTs (debe ir fuera del prefix para evitar conflicto)
    Route::get('/work-order-materials-inventory', [WorkOrderController::class, 'getAllMaterials']);
    
    Route::prefix('work-orders')->group(function () {
        // Exportación Excel
        Route::get('/export', [WorkOrderController::class, 'export']);

        // Estadísticas (debe ir antes de {id} para evitar conflicto)
        Route::get('/stats/overview', [WorkOrderController::class, 'stats']);
        
        // Rutas CRUD principales
        Route::get('/', [WorkOrderController::class, 'index']);
        Route::post('/', [WorkOrderController::class, 'store']);
        Route::get('/{id}/pdf', [WorkOrderController::class, 'generatePdf']);
        Route::get('/{id}', [WorkOrderController::class, 'show']);
        Route::put('/{id}', [WorkOrderController::class, 'update']);
        Route::delete('/{id}', [WorkOrderController::class, 'destroy']);
        
        // Rutas de gestión del ciclo de vida
        Route::post('/{id}/assign', [WorkOrderController::class, 'assign']);
        Route::post('/{id}/start', [WorkOrderController::class, 'start']);
        Route::post('/{id}/pause', [WorkOrderController::class, 'pause']);
        Route::post('/{id}/resume', [WorkOrderController::class, 'resume']);
        Route::post('/{id}/complete', [WorkOrderController::class, 'complete']);
        Route::post('/{id}/validate', [WorkOrderController::class, 'validate']);
        Route::post('/{id}/cancel', [WorkOrderController::class, 'cancel']);
        Route::post('/{id}/reopen', [WorkOrderController::class, 'reopen']);
        
        // Asignaciones de equipo (team members)
        Route::get('/{id}/assignments', [WorkOrderController::class, 'getAssignments']);
        Route::post('/{id}/assignments', [WorkOrderController::class, 'addAssignment']);
        Route::delete('/{id}/assignments/{assignmentId}', [WorkOrderController::class, 'removeAssignment']);
        
        // Alias para team-members (mismo funcionalidad que assignments)
        Route::get('/{id}/team-members', [WorkOrderController::class, 'getAssignments']);
        Route::post('/{id}/team-members', [WorkOrderController::class, 'addAssignment']);
        Route::delete('/{id}/team-members/{assignmentId}', [WorkOrderController::class, 'removeAssignment']);
        
        // Materiales y Herramientas - Flujo completo
        Route::get('/{id}/materials', [WorkOrderController::class, 'getMaterials']);
        Route::post('/{id}/materials', [WorkOrderController::class, 'addMaterial']);
        Route::put('/{id}/materials/{materialId}', [WorkOrderController::class, 'updateMaterial']);
        Route::delete('/{id}/materials/{materialId}', [WorkOrderController::class, 'removeMaterial']);
        
        // Flujo de materiales: Solicitud → Aprobación → Entrega → Consumo → Devolución
        Route::post('/{id}/materials/{materialId}/request', [WorkOrderController::class, 'requestMaterial']);
        Route::put('/{id}/materials/{materialId}/approve', [WorkOrderController::class, 'approveMaterial']);
        Route::put('/{id}/materials/{materialId}/deliver', [WorkOrderController::class, 'deliverMaterial']);
        Route::put('/{id}/materials/{materialId}/consume', [WorkOrderController::class, 'consumeMaterial']);
        Route::put('/{id}/materials/{materialId}/return', [WorkOrderController::class, 'returnMaterial']);
        Route::put('/{id}/materials/{materialId}/receive', [WorkOrderController::class, 'receiveMaterial']);
        
        // Registros de tiempo
        Route::get('/{id}/time-logs', [WorkOrderController::class, 'getTimeLogs']);
        Route::post('/{id}/time-logs', [WorkOrderController::class, 'addTimeLog']);
        Route::put('/{id}/time-logs/{logId}', [WorkOrderController::class, 'updateTimeLog']);
        Route::delete('/{id}/time-logs/{logId}', [WorkOrderController::class, 'removeTimeLog']);
        
        // Archivos adjuntos
        Route::get('/{id}/attachments', [WorkOrderController::class, 'getAttachments']);
        Route::post('/{id}/attachments', [WorkOrderController::class, 'uploadAttachments']);
        Route::delete('/{id}/attachments/{attachmentId}', [WorkOrderController::class, 'destroyAttachment']);
        
        // Checklist
        Route::get('/{id}/checklist', [WorkOrderController::class, 'getChecklist']);
        Route::post('/{id}/checklist', [WorkOrderController::class, 'addChecklistItem']);
        Route::patch('/{id}/checklist/{itemId}', [WorkOrderController::class, 'toggleChecklistItem']);
        Route::put('/{id}/checklist/{itemId}', [WorkOrderController::class, 'updateChecklistItemText']);
        Route::delete('/{id}/checklist/{itemId}', [WorkOrderController::class, 'deleteChecklistItem']);
        Route::put('/{id}/checklist/{itemId}/notes', [WorkOrderController::class, 'updateChecklistNotes']);
        
        // Comentarios
        Route::get('/{id}/comments', [WorkOrderController::class, 'getComments']);
        Route::post('/{id}/comments', [WorkOrderController::class, 'storeComment']);
        Route::put('/{id}/comments/{commentId}', [WorkOrderController::class, 'updateComment']);
        Route::delete('/{id}/comments/{commentId}', [WorkOrderController::class, 'destroyComment']);
        
        // Historial de estados
        Route::get('/{id}/status-history', [WorkOrderController::class, 'getStatusHistory']);
    });

    // ========================================
    // MÓDULO: MEDIDORES DE ACTIVOS (ASSET METERS)
    // ========================================
    Route::prefix('asset-meters')->group(function () {
        // Estadísticas (debe ir antes de {id} para evitar conflicto)
        Route::get('/statistics', [AssetMeterController::class, 'getStatistics']);
        
        // Rutas CRUD principales
        Route::get('/', [AssetMeterController::class, 'index']);
        Route::post('/', [AssetMeterController::class, 'store']);
        Route::get('/{id}', [AssetMeterController::class, 'show']);
        Route::put('/{id}', [AssetMeterController::class, 'update']);
        Route::delete('/{id}', [AssetMeterController::class, 'destroy']);
        
        // Activación/Desactivación
        Route::post('/{id}/activate', [AssetMeterController::class, 'activate']);
        Route::post('/{id}/deactivate', [AssetMeterController::class, 'deactivate']);
        
        // Gestión de lecturas
        Route::post('/{id}/readings', [AssetMeterController::class, 'recordReading']);
        Route::get('/{id}/readings', [AssetMeterController::class, 'getReadings']);
    });

    // ========================================
    // MÓDULO: PLANES DE MANTENIMIENTO (MAINTENANCE PLANS)
    // ========================================
    Route::prefix('maintenance-plans')->group(function () {
        // Dashboard y verificación de vencimientos (deben ir antes de {id})
        Route::get('/dashboard', [MaintenancePlanController::class, 'getDashboard']);
        Route::post('/check-due', [MaintenancePlanController::class, 'checkDuePlans']);
        
        // Rutas CRUD principales
        Route::get('/', [MaintenancePlanController::class, 'index']);
        Route::post('/', [MaintenancePlanController::class, 'store']);
        Route::get('/{id}', [MaintenancePlanController::class, 'show']);
        Route::put('/{id}', [MaintenancePlanController::class, 'update']);
        Route::delete('/{id}', [MaintenancePlanController::class, 'destroy']);
        
        // Activación/Desactivación
        Route::post('/{id}/activate', [MaintenancePlanController::class, 'activate']);
        Route::post('/{id}/deactivate', [MaintenancePlanController::class, 'deactivate']);
        
        // Ejecución de planes
        Route::post('/{id}/execute', [MaintenancePlanController::class, 'executeManually']);
        Route::get('/{id}/executions', [MaintenancePlanController::class, 'getExecutions']);
    });

    // ========================================
    // MÓDULO: LÍNEAS DE PRODUCCIÓN (HU-A1)
    // ========================================
    Route::prefix('production-lines')->group(function () {
        Route::get('/', [ProductionLineController::class, 'index']);
        Route::post('/', [ProductionLineController::class, 'store']);
        Route::get('/{id}', [ProductionLineController::class, 'show']);
        Route::put('/{id}', [ProductionLineController::class, 'update']);
        Route::delete('/{id}', [ProductionLineController::class, 'destroy']);
    });

    // ========================================
    // MÓDULO: CATEGORÍAS DE ACTIVOS
    // ========================================
    Route::prefix('asset-categories')->group(function () {
        Route::get('/', [AssetCategoryController::class, 'index']);
        Route::post('/', [AssetCategoryController::class, 'store']);
        Route::get('/{id}', [AssetCategoryController::class, 'show']);
        Route::put('/{id}', [AssetCategoryController::class, 'update']);
        Route::delete('/{id}', [AssetCategoryController::class, 'destroy']);
    });

    // ========================================
    // MÓDULO: SISTEMAS DE ACTIVOS
    // ========================================
    Route::prefix('asset-systems')->group(function () {
        Route::get('/', [AssetSystemController::class, 'index']);
        Route::post('/', [AssetSystemController::class, 'store']);
        Route::get('/{id}', [AssetSystemController::class, 'show']);
        Route::put('/{id}', [AssetSystemController::class, 'update']);
        Route::delete('/{id}', [AssetSystemController::class, 'destroy']);
    });

    // ========================================
    // MÓDULO: TIPOS DE MANTENIMIENTO (HU-A4)
    // ========================================
    Route::prefix('maintenance-types')->group(function () {
        Route::get('/', [MaintenanceTypeController::class, 'index']);
        Route::post('/', [MaintenanceTypeController::class, 'store']);
        Route::get('/{id}', [MaintenanceTypeController::class, 'show']);
        Route::put('/{id}', [MaintenanceTypeController::class, 'update']);
        Route::delete('/{id}', [MaintenanceTypeController::class, 'destroy']);
    });

    // ========================================
    // MÓDULO: FABRICANTES Y PROVEEDORES (HU-A5)
    // ========================================
    Route::prefix('asset-vendors')->group(function () {
        Route::get('/', [AssetVendorController::class, 'index']);
        Route::post('/', [AssetVendorController::class, 'store']);
        Route::get('/{id}', [AssetVendorController::class, 'show']);
        Route::put('/{id}', [AssetVendorController::class, 'update']);
        Route::delete('/{id}', [AssetVendorController::class, 'destroy']);
    });

    // ========================================
    // MÓDULO: INSPECCIONES PREOPERACIONALES
    // ========================================
    Route::prefix('inspection-shifts')->group(function () {
        Route::get('/', [InspectionShiftController::class, 'index']);
        Route::post('/', [InspectionShiftController::class, 'store']);
        Route::get('/{id}', [InspectionShiftController::class, 'show']);
        Route::put('/{id}', [InspectionShiftController::class, 'update']);
        Route::delete('/{id}', [InspectionShiftController::class, 'destroy']);
    });

    Route::prefix('inspection-templates')->group(function () {
        Route::get('/', [InspectionTemplateController::class, 'index']);
        Route::post('/', [InspectionTemplateController::class, 'store']);
        Route::get('/assets-by-category', [InspectionTemplateController::class, 'assetsByCategory']);
        // static routes must come BEFORE /{id} to avoid route conflict
        Route::post('/bulk-import', [InspectionTemplateController::class, 'bulkImport']);
        Route::get('/bulk-import-template', [InspectionTemplateController::class, 'downloadTemplate']);
        Route::get('/{id}', [InspectionTemplateController::class, 'show']);
        Route::put('/{id}', [InspectionTemplateController::class, 'update']);
        Route::delete('/{id}', [InspectionTemplateController::class, 'destroy']);
        Route::put('/{id}/sections', [InspectionTemplateController::class, 'storeSections']);
        Route::put('/{id}/assets', [InspectionTemplateController::class, 'syncAssets']);
        Route::get('/{id}/by-asset/{assetId}', [InspectionTemplateController::class, 'getByAsset']);
    });

    Route::prefix('inspections')->group(function () {
        Route::get('/', [InspectionController::class, 'index']);
        Route::post('/', [InspectionController::class, 'store']);
        Route::get('/{id}', [InspectionController::class, 'show']);
        Route::put('/{id}', [InspectionController::class, 'update']);
        Route::put('/{id}/responses', [InspectionController::class, 'saveResponses']);
        Route::post('/{id}/complete', [InspectionController::class, 'complete']);
        Route::post('/{id}/work-request', [InspectionController::class, 'generateWorkRequest']);
        Route::post('/{id}/responses/{responseId}/photos', [InspectionController::class, 'uploadPhoto']);
        Route::delete('/{id}', [InspectionController::class, 'destroy']);
        Route::delete('/{id}/responses/{responseId}/photos/{photoId}', [InspectionController::class, 'deletePhoto']);
    });

    Route::get('assets/{assetId}/inspection-template', [InspectionTemplateController::class, 'getByAsset']);

    // ========================================
    // PROYECTOS Y MONTAJES
    // ========================================
    Route::prefix('projects')->group(function () {
        // Catálogos (para formularios)
        Route::get('/catalogs', [\App\Http\Controllers\Api\ProjectController::class, 'catalogs']);

        // CRUD principal
        Route::get('/', [\App\Http\Controllers\Api\ProjectController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\ProjectController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'destroy']);

        // Transiciones de estado
        Route::post('/{id}/approve', [\App\Http\Controllers\Api\ProjectController::class, 'approve']);
        Route::post('/{id}/start', [\App\Http\Controllers\Api\ProjectController::class, 'start']);
        Route::post('/{id}/pause', [\App\Http\Controllers\Api\ProjectController::class, 'pause']);
        Route::post('/{id}/finish', [\App\Http\Controllers\Api\ProjectController::class, 'finish']);
        Route::post('/{id}/close', [\App\Http\Controllers\Api\ProjectController::class, 'close']);
        Route::post('/{id}/cancel', [\App\Http\Controllers\Api\ProjectController::class, 'cancel']);

        // Resumen / indicadores
        Route::get('/{id}/summary', [\App\Http\Controllers\Api\ProjectController::class, 'summary']);

        // Fases
        Route::get('/{id}/phases', [\App\Http\Controllers\Api\ProjectPhaseController::class, 'index']);
        Route::post('/{id}/phases', [\App\Http\Controllers\Api\ProjectPhaseController::class, 'store']);
        Route::put('/{id}/phases/{phaseId}', [\App\Http\Controllers\Api\ProjectPhaseController::class, 'update']);
        Route::delete('/{id}/phases/{phaseId}', [\App\Http\Controllers\Api\ProjectPhaseController::class, 'destroy']);
        Route::post('/{id}/phases/{phaseId}/status', [\App\Http\Controllers\Api\ProjectPhaseController::class, 'changeStatus']);
        Route::patch('/{id}/phases/{phaseId}/progress', [\App\Http\Controllers\Api\ProjectPhaseController::class, 'updateProgress']);
        Route::get('/{id}/phases/{phaseId}/status-history', [\App\Http\Controllers\Api\ProjectPhaseController::class, 'statusHistory']);

        // Recursos por fase
        Route::get('/{id}/phases/{phaseId}/resources', [\App\Http\Controllers\Api\ProjectPhaseResourceController::class, 'index']);
        Route::post('/{id}/phases/{phaseId}/resources', [\App\Http\Controllers\Api\ProjectPhaseResourceController::class, 'store']);
        Route::put('/{id}/phases/{phaseId}/resources/{resourceId}', [\App\Http\Controllers\Api\ProjectPhaseResourceController::class, 'update']);
        Route::delete('/{id}/phases/{phaseId}/resources/{resourceId}', [\App\Http\Controllers\Api\ProjectPhaseResourceController::class, 'destroy']);

        // Miembros del equipo
        Route::get('/{id}/members', [\App\Http\Controllers\Api\ProjectMemberController::class, 'index']);
        Route::post('/{id}/members', [\App\Http\Controllers\Api\ProjectMemberController::class, 'store']);
        Route::put('/{id}/members/{memberId}', [\App\Http\Controllers\Api\ProjectMemberController::class, 'update']);
        Route::delete('/{id}/members/{memberId}', [\App\Http\Controllers\Api\ProjectMemberController::class, 'destroy']);

        // Bitácora / PDT
        Route::get('/{id}/logs', [\App\Http\Controllers\Api\ProjectLogController::class, 'index']);
        Route::post('/{id}/logs', [\App\Http\Controllers\Api\ProjectLogController::class, 'store']);
        Route::put('/{id}/logs/{logId}', [\App\Http\Controllers\Api\ProjectLogController::class, 'update']);
        Route::post('/{id}/logs/{logId}/review', [\App\Http\Controllers\Api\ProjectLogController::class, 'review']);
        Route::post('/{id}/logs/{logId}/validate', [\App\Http\Controllers\Api\ProjectLogController::class, 'validate']);

        // Adjuntos de bitácora
        Route::post('/{id}/logs/{logId}/attachments', [\App\Http\Controllers\Api\ProjectLogAttachmentController::class, 'store']);
        Route::delete('/{id}/logs/{logId}/attachments/{attachmentId}', [\App\Http\Controllers\Api\ProjectLogAttachmentController::class, 'destroy']);

        // Exportar bitácora
        Route::get('/{id}/logs/export', [\App\Http\Controllers\Api\ProjectLogController::class, 'exportPdf']);
    });

    // ========================================
    // CATÁLOGOS DEL SISTEMA
    // ========================================
    Route::prefix('catalogs')->group(function () {
        Route::get('/units-of-measure', [\App\Http\Controllers\Api\CatalogController::class, 'getUnitsOfMeasure']);
        Route::get('/warehouse-types', [\App\Http\Controllers\Api\CatalogController::class, 'getWarehouseTypes']);
        Route::get('/transaction-types', [\App\Http\Controllers\Api\CatalogController::class, 'getTransactionTypes']);
    });

    // ========================================
    // FCM PUSH NOTIFICATION TOKENS
    // ========================================
    Route::post('/fcm-tokens', [\App\Http\Controllers\Api\DeviceTokenController::class, 'store']);
    Route::delete('/fcm-tokens', [\App\Http\Controllers\Api\DeviceTokenController::class, 'destroy']);

    // ========================================
    // NOTIFICACIONES IN-APP
    // ========================================
    Route::prefix('notifications')->group(function () {
        Route::get('/',             [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::patch('/read-all',   [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);
        Route::patch('/{id}/read',  [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
    });
});

// Ruta de salud del API
Route::get('/health', function () {
    return \App\Http\Responses\ApiResponse::success([
        'status' => 'ok',
        'service' => 'RECYLO System API'
    ], 'API funcionando correctamente');
});