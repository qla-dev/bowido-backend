<?php

namespace Tests\Feature;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Shared\Enums\ModuleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_without_permission_is_forbidden_from_listing_roles(): void
    {
        $operator = $this->makeUser('operator');

        $this->actingAs($operator, 'api')
            ->getJson('/api/roles')
            ->assertForbidden();
    }

    public function test_customer_cannot_view_another_customers_invoice(): void
    {
        $customerRole = $this->role('customer');
        $this->grantPermissions($customerRole, [ModuleKey::Invoices->value], [
            'can_list' => true,
            'can_view' => true,
        ]);

        $customerA = $this->makeUser('customer');
        $customerB = $this->makeUser('customer');
        $invoice = Invoice::factory()->create(['user_id' => $customerB->id]);

        $this->actingAs($customerA, 'api')
            ->getJson('/api/invoices/'.$invoice->id)
            ->assertForbidden();
    }
}
