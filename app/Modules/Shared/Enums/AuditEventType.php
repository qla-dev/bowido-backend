<?php

namespace App\Modules\Shared\Enums;

enum AuditEventType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case ClientChanged = 'client_changed';
    case LocationChanged = 'location_changed';
    case QrCodeChanged = 'qr_code_changed';
    case GhostPaired = 'ghost_pallet_paired';
    case Updated = 'updated';
    case Deleted = 'deleted';
}
