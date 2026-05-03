<?php

namespace App\Modules\Shared\Enums;

enum ServiceReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
