<?php

namespace App\Modules\Shared\Enums;

enum ModuleKey: string
{
    case Roles = 'roles';
    case Users = 'users';
    case CustomerDetails = 'customer_details';
    case Statuses = 'statuses';
    case Pallets = 'pallets';
    case AuditLogs = 'audit_logs';
    case ServiceReports = 'service_reports';
    case GhostPalletReports = 'ghost_pallet_reports';
    case Invoices = 'invoices';
    case InvoiceItems = 'invoice_items';
    case Modules = 'modules';
    case RolePermissions = 'role_permissions';
}
