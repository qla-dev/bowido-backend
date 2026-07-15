<?php

use App\Modules\AuditLogs\Controllers\AuditLogController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\CalendarNotes\Controllers\CalendarNoteController;
use App\Modules\CustomerDetails\Controllers\CustomerDetailController;
use App\Modules\GhostPalletReports\Controllers\GhostPalletReportController;
use App\Modules\InvoiceItems\Controllers\InvoiceItemController;
use App\Modules\Invoices\Controllers\InvoiceController;
use App\Modules\Modules\Controllers\ModuleController;
use App\Modules\PalletPhotos\Controllers\PalletPhotoController;
use App\Modules\Pallets\Controllers\PalletController;
use App\Modules\Pallets\Controllers\PalletStatsController;
use App\Modules\RolePermissions\Controllers\RolePermissionController;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\ServiceReports\Controllers\ServiceReportController;
use App\Modules\Statuses\Controllers\StatusController;
use App\Modules\Users\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => response()->json([
    'status' => 'ok',
]));

Route::get('pallet-photos/{palletPhoto}/file', [PalletPhotoController::class, 'file'])
    ->middleware('signed')
    ->name('pallet-photos.file');

Route::prefix('auth')->group(function (): void {
    Route::get('login-options', [AuthController::class, 'loginOptions']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:web,api')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:web,api')->group(function (): void {
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('customer_details', CustomerDetailController::class)
        ->parameters(['customer_details' => 'customerDetail']);
    Route::apiResource('statuses', StatusController::class);
    Route::get('pallets/dashboard-stats', PalletStatsController::class);
    Route::apiResource('pallets', PalletController::class);
    Route::post('pallets/{pallet}/photos', [PalletPhotoController::class, 'store']);
    Route::get('customer_details/{customerDetail}/pallet-photos', [PalletPhotoController::class, 'forCustomer']);
    Route::apiResource('audit_logs', AuditLogController::class)
        ->parameters(['audit_logs' => 'auditLog']);
    Route::apiResource('service_reports', ServiceReportController::class)
        ->parameters(['service_reports' => 'serviceReport']);
    Route::apiResource('ghost_pallet_reports', GhostPalletReportController::class)
        ->parameters(['ghost_pallet_reports' => 'ghostPalletReport']);
    Route::get('calendar_notes/notify-candidates', [CalendarNoteController::class, 'notifyCandidates']);
    Route::apiResource('calendar_notes', CalendarNoteController::class)
        ->parameters(['calendar_notes' => 'calendarNote']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('invoice_items', InvoiceItemController::class)
        ->parameters(['invoice_items' => 'invoiceItem']);
    Route::apiResource('modules', ModuleController::class);
    Route::apiResource('role_permissions', RolePermissionController::class)
        ->parameters(['role_permissions' => 'rolePermission']);
});
