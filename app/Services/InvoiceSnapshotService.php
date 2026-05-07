<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvoiceSnapshotService
{
    public function __construct(
        private readonly BillingCounterService $billingCounterService,
        private readonly InvoiceGenerationService $invoiceGenerationService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(
        User $customer,
        CarbonInterface $billingPeriodEnd,
        ?CarbonInterface $billingPeriodStart = null,
        string $currency = 'EUR',
    ): array {
        $resolvedStart = $this->resolveBillingPeriodStart($customer, $billingPeriodEnd, $billingPeriodStart);

        $this->assertBillingPeriodIsAvailable($customer, $resolvedStart, $billingPeriodEnd);

        return $this->invoiceGenerationService->preview(
            customer: $customer,
            periodStart: $resolvedStart,
            periodEnd: $billingPeriodEnd,
            currency: $currency,
        );
    }

    public function sendSnapshot(
        int $customerId,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
        bool $markAsSent = true,
        ?string $note = null,
        string $currency = 'EUR',
    ): Invoice {
        /** @var User $customer */
        $customer = User::query()->findOrFail($customerId);

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => ['The selected customer is not active.'],
            ]);
        }

        $this->assertBillingPeriodIsAvailable($customer, $billingPeriodStart, $billingPeriodEnd);

        return $this->invoiceGenerationService->generate(
            customer: $customer,
            periodStart: $billingPeriodStart,
            periodEnd: $billingPeriodEnd,
            dueAt: $markAsSent ? Carbon::parse($billingPeriodEnd)->addDays(14) : null,
            currency: $currency,
            notes: $note,
            billingPeriodStart: $billingPeriodStart,
            billingPeriodEnd: $billingPeriodEnd,
            status: $markAsSent ? Invoice::STATUS_ISSUED : Invoice::STATUS_DRAFT,
        );
    }

    public function resolveBillingPeriodStart(
        User $customer,
        CarbonInterface $billingPeriodEnd,
        ?CarbonInterface $billingPeriodStart = null,
    ): CarbonInterface {
        $resolvedStart = $billingPeriodStart instanceof CarbonInterface
            ? Carbon::parse($billingPeriodStart)->startOfDay()
            : $this->billingCounterService->defaultBillingPeriodStartForCustomer($customer, $billingPeriodEnd);

        if ($resolvedStart->gt(Carbon::parse($billingPeriodEnd)->startOfDay())) {
            throw new BadRequestHttpException('The billing period start date cannot be after the billing period end date.');
        }

        return $resolvedStart;
    }

    private function assertBillingPeriodIsAvailable(
        User $customer,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
    ): void {
        $hasOverlap = Invoice::query()
            ->where('user_id', $customer->id)
            ->whereNotNull('billing_period_start')
            ->whereNotNull('billing_period_end')
            ->whereDate('billing_period_start', '<=', $billingPeriodEnd->toDateString())
            ->whereDate('billing_period_end', '>=', $billingPeriodStart->toDateString())
            ->exists();

        if ($hasOverlap) {
            throw new BadRequestHttpException('The selected billing period overlaps with an existing invoice snapshot.');
        }
    }
}