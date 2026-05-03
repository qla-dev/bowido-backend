<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Invoices\DTOs\InvoiceData;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Repositories\InvoiceRepository;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Shared\Services\InvoiceGenerationService;
use App\Modules\Users\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class InvoiceService extends BaseCrudService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly UserRepository $userRepository,
        private readonly InvoiceGenerationService $invoiceGenerationService,
    ) {
        parent::__construct($invoiceRepository);
    }

    public function create(InvoiceData $data): Invoice
    {
        $user = $this->userRepository->findOrFail($data->userId);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not active.'],
            ]);
        }

        return $this->invoiceGenerationService->generate(
            customer: $user,
            periodStart: Carbon::parse($data->periodStart),
            periodEnd: Carbon::parse($data->periodEnd),
            dueAt: $data->dueAt !== null ? Carbon::parse($data->dueAt) : null,
            currency: $data->currency,
            notes: $data->notes,
        );
    }

    public function update(Invoice $invoice, InvoiceData $data): Invoice
    {
        $user = $this->userRepository->findOrFail($data->userId);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not active.'],
            ]);
        }

        return $this->invoiceGenerationService->generate(
            customer: $user,
            periodStart: Carbon::parse($data->periodStart),
            periodEnd: Carbon::parse($data->periodEnd),
            dueAt: $data->dueAt !== null ? Carbon::parse($data->dueAt) : null,
            currency: $data->currency,
            notes: $data->notes,
            invoice: $invoice,
        );
    }
}
