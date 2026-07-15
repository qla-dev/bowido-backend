<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Modules\PalletPhotos\Services\PalletPhotoService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pallet-photos:prune', function (PalletPhotoService $palletPhotoService): void {
    $deleted = $palletPhotoService->pruneExpired();

    $this->info("Deleted {$deleted} expired pallet photo(s).");
})->purpose('Delete pallet photos that have reached their retention date.');

Schedule::command('pallet-photos:prune')->dailyAt('02:15')->withoutOverlapping();
