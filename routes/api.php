<?php

use App\Modules\AuditLogs\Controllers\AuditLogController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\CustomerDetails\Controllers\CustomerDetailController;
use App\Modules\GhostPalletReports\Controllers\GhostPalletReportController;
use App\Modules\InvoiceItems\Controllers\InvoiceItemController;
use App\Modules\Invoices\Controllers\InvoiceController;
use App\Modules\Modules\Controllers\ModuleController;
use App\Modules\Pallets\Controllers\PalletController;
use App\Modules\RolePermissions\Controllers\RolePermissionController;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\ServiceReports\Controllers\ServiceReportController;
use App\Modules\Statuses\Controllers\StatusController;
use App\Modules\Users\Controllers\UserController;
use Illuminate\Support\Facades\Route;

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
    Route::apiResource('pallets', PalletController::class);
    Route::apiResource('audit_logs', AuditLogController::class)
        ->parameters(['audit_logs' => 'auditLog']);
    Route::apiResource('service_reports', ServiceReportController::class)
        ->parameters(['service_reports' => 'serviceReport']);
    Route::apiResource('ghost_pallet_reports', GhostPalletReportController::class)
        ->parameters(['ghost_pallet_reports' => 'ghostPalletReport']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('invoice_items', InvoiceItemController::class)
        ->parameters(['invoice_items' => 'invoiceItem']);
    Route::apiResource('modules', ModuleController::class);
    Route::apiResource('role_permissions', RolePermissionController::class)
        ->parameters(['role_permissions' => 'rolePermission']);
});
