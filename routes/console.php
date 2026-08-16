<?php

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\InvoiceDeliveryService;
use App\Modules\Invoices\Services\OverduePalletInvoiceService;
use App\Modules\PalletPhotos\Services\PalletPhotoService;
use App\Modules\Shared\Enums\InvoiceStatus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pallet-photos:prune', function (PalletPhotoService $palletPhotoService): void {
    $deleted = $palletPhotoService->pruneExpired();

    $this->info("Deleted {$deleted} expired pallet photo(s).");
})->purpose('Delete pallet photos that have reached their retention date.');

Schedule::command('pallet-photos:prune')->dailyAt('02:15')->withoutOverlapping();

Artisan::command('invoices:generate-previous-month', function (OverduePalletInvoiceService $invoiceService): void {
    $invoices = $invoiceService->generateForMonth(now()->subMonth());

    $this->info("Created or updated {$invoices->count()} customer invoice(s) for the previous month.");
})->purpose('Create one invoice per customer for pallets that remained overdue during the previous month.');

Schedule::command('invoices:generate-previous-month')->everyMinute()->withoutOverlapping();

Artisan::command('invoices:send-previous-month', function (InvoiceDeliveryService $deliveryService): void {
    $currentMonth = now()->startOfMonth();
    $previousMonth = $currentMonth->copy()->subMonth();
    $sent = 0;
    $failed = 0;

    Invoice::query()
        ->where('status', InvoiceStatus::Issued->value)
        ->whereNull('mailed_at')
        ->where(function ($query) use ($previousMonth, $currentMonth): void {
            $query
                ->where(function ($periodQuery) use ($previousMonth): void {
                    $periodQuery
                        ->whereDate('period_start', $previousMonth->toDateString())
                        ->whereDate('period_end', $previousMonth->copy()->endOfMonth()->toDateString());
                })
                // Keep legacy partial-period automatic invoices deliverable.
                ->orWhere(function ($createdQuery) use ($previousMonth, $currentMonth): void {
                    $createdQuery
                        ->where('created_at', '>=', $previousMonth)
                        ->where('created_at', '<', $currentMonth);
                });
        })
        ->orderBy('id')
        ->eachById(function (Invoice $invoice) use ($deliveryService, &$sent, &$failed): void {
            try {
                $deliveryService->send($invoice, 'monthly_previous_month');
                $sent++;
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
            }
        });

    $this->info("Sent {$sent} invoice(s); {$failed} failed.");
})->purpose('Email invoices that were created during the previous calendar month.');

Schedule::command('invoices:send-previous-month')->everyMinute()->withoutOverlapping();
