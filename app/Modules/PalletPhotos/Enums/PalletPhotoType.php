<?php

namespace App\Modules\PalletPhotos\Enums;

enum PalletPhotoType: string
{
    case Scan = 'scan';
    case DamageReport = 'damage_report';
    case ServiceReport = 'service_report';
}
