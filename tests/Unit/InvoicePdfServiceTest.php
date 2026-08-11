<?php

namespace Tests\Unit;

use App\Modules\Invoices\Services\InvoicePdfService;
use PHPUnit\Framework\TestCase;

class InvoicePdfServiceTest extends TestCase
{
    public function test_expected_return_date_uses_the_invoice_grace_period_snapshot(): void
    {
        $returnDate = (new InvoicePdfService)->expectedReturnDate([
            'received_date' => '2026-07-21',
            'grace_period_days' => 14,
        ], '2026-07-01', 3);

        $this->assertSame('2026-08-04', $returnDate?->toDateString());
    }

    public function test_expected_return_date_uses_the_current_client_grace_period_for_legacy_invoice_items(): void
    {
        $returnDate = (new InvoicePdfService)->expectedReturnDate(
            ['customer_since' => '2026-07-21'],
            '2026-07-01',
            5,
        );

        $this->assertSame('2026-07-26', $returnDate?->toDateString());
    }
}
