<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerDetailController;
use App\Http\Controllers\GhostPalletReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PalletController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ServiceReportController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::get('pallets/scan/{qrCode}', [PalletController::class, 'scan']);
    Route::post('pallets/bulk-change-status', [PalletController::class, 'bulkChangeStatus']);
    Route::post('pallets/{pallet}/change-status', [PalletController::class, 'changeStatus']);
    Route::post('pallets/{pallet}/mark-ready-for-return', [PalletController::class, 'markReadyForReturn']);
    Route::post('pallets/{pallet}/mark-unknown', [PalletController::class, 'markUnknown']);
    Route::get('pallets/returnable', [PalletController::class, 'returnable']);
    Route::get('pallets/filter', [PalletController::class, 'filter']);
    Route::get('pallets/overdue', [PalletController::class, 'overdue']);
    Route::get('pallets/by-customer/{customer}', [PalletController::class, 'byCustomer']);
    Route::get('pallets/service-list', [PalletController::class, 'serviceList']);
    Route::get('pallets/{pallet}/history', [AuditLogController::class, 'history']);
    Route::get('pallets/{pallet}/qr-versions', [AuditLogController::class, 'qrVersions']);
    Route::get('audit-logs/filter', [AuditLogController::class, 'filter']);
    Route::get('counters/pallet/{pallet}', [PalletController::class, 'counter']);

    Route::get('customers/search', [CustomerDetailController::class, 'search']);
    Route::get('customers/kvk/{kvk}', [CustomerDetailController::class, 'byKvk']);
    Route::get('customers/{customer}/current-costs', [CustomerDetailController::class, 'currentCosts']);
    Route::get('notifications/weekly-digest/preview/{customer}', [CustomerDetailController::class, 'weeklyDigestPreview']);
    Route::post('notifications/weekly-digest/run', [CustomerDetailController::class, 'runWeeklyDigest']);

    Route::get('invoices/preview/{customer}', [InvoiceController::class, 'preview']);
    Route::post('invoices/send-snapshot', [InvoiceController::class, 'sendSnapshot']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::get('reports/invoices/excel', [InvoiceController::class, 'invoicesExcel']);

    Route::post('service/pallets/{pallet}/report', [ServiceReportController::class, 'report']);
    Route::post('service/pallets/{pallet}/resolve', [ServiceReportController::class, 'resolve']);

    Route::post('ghost-pallets/report', [GhostPalletReportController::class, 'report']);
    Route::post('ghost-pallets/{ghostPalletReport}/pair', [GhostPalletReportController::class, 'pair']);
    Route::get('ghost-pallets/active', [GhostPalletReportController::class, 'active']);

    Route::get('help/my-role', [RoleController::class, 'myRoleHelp']);
    Route::get('help/by-role/{role}', [RoleController::class, 'roleHelpPreview']);

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