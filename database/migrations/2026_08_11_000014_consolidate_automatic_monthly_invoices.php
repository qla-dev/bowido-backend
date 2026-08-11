<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('invoices')
                ->where(function ($query): void {
                    $query
                        ->where('invoice_number', 'like', 'INV-OVD-%')
                        ->orWhere('invoice_number', 'like', 'INV-%-C%');
                })
                ->orderBy('id')
                ->get()
                ->filter(fn (object $invoice): bool => $this->isAutomaticNumber($invoice->invoice_number))
                ->groupBy(fn (object $invoice): string => $invoice->user_id.'|'.$this->billingMonth($invoice))
                ->each(function (Collection $invoices): void {
                    $ordered = $invoices->sortBy(fn (object $invoice): string => sprintf(
                        '%d|%s|%020d',
                        str_starts_with($invoice->invoice_number, 'INV-OVD-') ? 0 : 1,
                        $invoice->created_at,
                        $invoice->id,
                    ))->values();
                    $target = $ordered->first();

                    // Some legacy invoices reference customers removed by an
                    // old import process. Updating those rows makes MySQL
                    // revalidate the foreign key, so leave them untouched.
                    if (! DB::table('users')->where('id', $target->user_id)->exists()) {
                        return;
                    }

                    if (! str_starts_with($target->invoice_number, 'INV-OVD-')) {
                        $target->invoice_number = $this->nextOverdueNumber(Carbon::parse($target->created_at));
                        DB::table('invoices')->where('id', $target->id)->update([
                            'invoice_number' => $target->invoice_number,
                        ]);
                    }

                    foreach ($ordered->skip(1) as $duplicate) {
                        $this->mergeInvoiceItems($target->id, $duplicate->id);

                        if ($this->statusRank($duplicate->status) > $this->statusRank($target->status)) {
                            $target->status = $duplicate->status;
                        }

                        $target->mailed_at = collect([$target->mailed_at, $duplicate->mailed_at])->filter()->max();
                        $target->paid_at = collect([$target->paid_at, $duplicate->paid_at])->filter()->max();

                        DB::table('invoices')->where('id', $duplicate->id)->delete();
                    }

                    $monthStart = Carbon::createFromFormat('Y-m', $this->billingMonth($target))->startOfMonth();
                    $total = DB::table('invoice_items')->where('invoice_id', $target->id)->sum('amount');

                    DB::table('invoices')->where('id', $target->id)->update([
                        'status' => $target->status,
                        'period_start' => $monthStart->toDateString(),
                        'period_end' => $monthStart->copy()->endOfMonth()->toDateString(),
                        'mailed_at' => $target->mailed_at,
                        'paid_at' => $target->paid_at,
                        'subtotal_amount' => number_format((float) $total, 2, '.', ''),
                        'total_amount' => number_format((float) $total, 2, '.', ''),
                        'updated_at' => now(),
                    ]);
                });
        });
    }

    public function down(): void
    {
        // Consolidated duplicate invoices cannot be reconstructed safely.
    }

    private function mergeInvoiceItems(int $targetInvoiceId, int $duplicateInvoiceId): void
    {
        DB::table('invoice_items')
            ->where('invoice_id', $duplicateInvoiceId)
            ->orderBy('id')
            ->get()
            ->each(function (object $duplicateItem) use ($targetInvoiceId): void {
                $existingItem = DB::table('invoice_items')
                    ->where('invoice_id', $targetInvoiceId)
                    ->where('pallet_id', $duplicateItem->pallet_id)
                    ->first();

                if ($existingItem === null) {
                    DB::table('invoice_items')->where('id', $duplicateItem->id)->update([
                        'invoice_id' => $targetInvoiceId,
                        'updated_at' => now(),
                    ]);

                    return;
                }

                if ((int) $duplicateItem->billed_days > (int) $existingItem->billed_days) {
                    DB::table('invoice_items')->where('id', $existingItem->id)->update([
                        'description' => $duplicateItem->description,
                        'period_start' => $duplicateItem->period_start,
                        'period_end' => $duplicateItem->period_end,
                        'billed_days' => $duplicateItem->billed_days,
                        'price_per_day' => $duplicateItem->price_per_day,
                        'amount' => $duplicateItem->amount,
                        'metadata' => $duplicateItem->metadata,
                        'updated_at' => now(),
                    ]);
                }

                DB::table('invoice_items')->where('id', $duplicateItem->id)->delete();
            });
    }

    private function billingMonth(object $invoice): string
    {
        $periodStart = Carbon::parse($invoice->period_start);
        $periodEnd = Carbon::parse($invoice->period_end);

        if ($periodStart->isStartOfMonth() && $periodEnd->isSameDay($periodStart->copy()->endOfMonth())) {
            return $periodStart->format('Y-m');
        }

        return Carbon::parse($invoice->created_at)->format('Y-m');
    }

    private function isAutomaticNumber(string $invoiceNumber): bool
    {
        return preg_match('/^INV-OVD-\d{8}-\d{4}$/', $invoiceNumber) === 1
            || preg_match('/^INV-\d{6}-C\d+$/', $invoiceNumber) === 1;
    }

    private function nextOverdueNumber(Carbon $createdAt): string
    {
        $sequence = 1;

        do {
            $invoiceNumber = sprintf('INV-OVD-%s-%04d', $createdAt->format('Ymd'), $sequence++);
        } while (DB::table('invoices')->where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'paid' => 4,
            'sent' => 3,
            'issued' => 2,
            'draft' => 1,
            default => 0,
        };
    }
};
