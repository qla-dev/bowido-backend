<?php

namespace App\Modules\Shared\Enums;

enum ModuleKey: string
{
    case Pallets = 'pallets';
    case Customers = 'customers';
    case Roles = 'roles';
    case AuditLogs = 'audit_logs';
    case Invoices = 'invoices';
    case InvoiceItems = 'invoice_items';
    case KnowledgeBase = 'knowledge_base';
    case Statuses = 'statuses';
    case QrVersions = 'qr_versions';
    case Services = 'services';
    case Users = 'users';
    case GhostPalletReports = 'ghost_pallet_reports';
}
