<?php

namespace App\Modules\InvoiceItems\Services;

use App\Modules\InvoiceItems\DTOs\InvoiceItemData;
use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\InvoiceItems\Repositories\InvoiceItemRepository;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Repositories\InvoiceRepository;
use App\Modules\Pallets\Repositories\PalletRepository;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Shared\Services\InvoiceGenerationService;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceItemService extends BaseCrudService
{
    public function __construct(
        private readonly InvoiceItemRepository $invoiceItemRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly PalletRepository $palletRepository,
        private readonly InvoiceGenerationService $invoiceGenerationService,
    ) {
        parent::__construct($invoiceItemRepository);
    }

    public function create(InvoiceItemData $data): InvoiceItem
    {
        return DB::transaction(function () use ($data): InvoiceItem {
            /** @var Invoice $invoice */
            $invoice = $this->invoiceRepository->findOrFail($data->invoiceId);

            if ($data->palletId !== null) {
                $pallet = $this->palletRepository->findOrFail($data->palletId);

                if ($pallet->user_id !== $invoice->user_id) {
                    throw ValidationException::withMessages([
                        'pallet_id' => [__('Invoice items must reference pallets owned by the invoiced user.')],
                    ]);
                }
            }

            /** @var InvoiceItem $invoiceItem */
            $invoiceItem = $this->invoiceItemRepository->create([
                'invoice_id' => $data->invoiceId,
                'pallet_id' => $data->palletId,
                'description' => $data->description,
                'period_start' => $data->periodStart,
                'period_end' => $data->periodEnd,
                'billed_days' => $data->billedDays,
                'price_per_day' => $data->pricePerDay,
                'amount' => $data->amount(),
                'metadata' => $data->metadata,
            ]);

            $this->invoiceGenerationService->recalculateTotals($invoice);

            return $invoiceItem->load(['invoice.user.role', 'pallet.currentStatus']);
        });
    }

    public function update(InvoiceItem $invoiceItem, InvoiceItemData $data): InvoiceItem
    {
        return DB::transaction(function () use ($invoiceItem, $data): InvoiceItem {
            /** @var Invoice $invoice */
            $invoice = $this->invoiceRepository->findOrFail($data->invoiceId);

            if ($data->palletId !== null) {
                $pallet = $this->palletRepository->findOrFail($data->palletId);

                if ($pallet->user_id !== $invoice->user_id) {
                    throw ValidationException::withMessages([
                        'pallet_id' => [__('Invoice items must reference pallets owned by the invoiced user.')],
                    ]);
                }
            }

            /** @var InvoiceItem $updatedInvoiceItem */
            $updatedInvoiceItem = $this->invoiceItemRepository->update($invoiceItem, [
                'invoice_id' => $data->invoiceId,
                'pallet_id' => $data->palletId,
                'description' => $data->description,
                'period_start' => $data->periodStart,
                'period_end' => $data->periodEnd,
                'billed_days' => $data->billedDays,
                'price_per_day' => $data->pricePerDay,
                'amount' => $data->amount(),
                'metadata' => $data->metadata,
            ]);

            $this->invoiceGenerationService->recalculateTotals($invoice);

            return $updatedInvoiceItem->load(['invoice.user.role', 'pallet.currentStatus']);
        });
    }

    public function delete(int $id, ?User $actor = null): void
    {
        DB::transaction(function () use ($id, $actor): void {
            /** @var InvoiceItem $invoiceItem */
            $invoiceItem = $this->invoiceItemRepository->findOrFail($id, $actor);
            $invoice = $invoiceItem->invoice;

            $this->invoiceItemRepository->delete($invoiceItem);

            if ($invoice instanceof Invoice) {
                $this->invoiceGenerationService->recalculateTotals($invoice);
            }
        });
    }
}
