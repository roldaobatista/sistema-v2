<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ImportAuvoQuotes;
use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use ReflectionClass;
use Tests\TestCase;

class ImportAuvoQuotesCommandTest extends TestCase
{
    public function test_map_status_supports_invoiced_from_auvo_legacy_fields(): void
    {
        $command = new ImportAuvoQuotes;
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('mapStatus');
        $method->setAccessible(true);

        $this->assertSame(
            QuoteStatus::INVOICED,
            $method->invoke($command, 'Faturado', '')
        );

        $this->assertSame(
            QuoteStatus::INVOICED,
            $method->invoke($command, 'Aberto', 'Faturado')
        );
    }

    public function test_create_quotes_populates_sent_and_approved_dates_for_invoiced_quotes(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $seller = User::factory()->create(['tenant_id' => $tenant->id]);

        $command = new ImportAuvoQuotes;
        $reflection = new ReflectionClass($command);

        $tenantProperty = $reflection->getProperty('tenantId');
        $tenantProperty->setAccessible(true);
        $tenantProperty->setValue($command, $tenant->id);

        $sellerProperty = $reflection->getProperty('sellerId');
        $sellerProperty->setAccessible(true);
        $sellerProperty->setValue($command, $seller->id);

        $createQuotesMethod = $reflection->getMethod('createQuotes');
        $createQuotesMethod->setAccessible(true);

        $createdAt = now()->subDays(10)->startOfDay();
        $approvedAt = now()->subDays(2)->setTime(15, 45);

        $quoteMap = $createQuotesMethod->invoke($command, [[
            'number' => 987,
            'created_at' => $createdAt,
            'seller' => 'Vendedor Auvo',
            'status' => 'Faturado',
            'status_updated_at' => $approvedAt,
            'customer_name' => $customer->name,
            'product_value' => '1000.00',
            'service_value' => '500.00',
            'additional_cost' => '0.00',
            'discount' => '50.00',
            'total' => '1450.00',
            'observations' => 'Observacao importada',
            'internal_notes' => 'Nota interna',
            'tasks' => 'Task 1',
            'expiration' => now()->addDays(20)->format('d/m/Y'),
            'payment_form' => 'Pix',
            'payment_condition' => '1x',
            'approval_status' => 'Faturado',
            'approval_date' => $approvedAt->format('d/m/Y H:i:s'),
            'rejection_reason' => '',
        ]], [
            $customer->name => $customer->id,
        ]);

        $this->assertArrayHasKey(987, $quoteMap);

        $quote = Quote::findOrFail($quoteMap[987]);

        $this->assertSame(Quote::STATUS_INVOICED, $quote->status->value ?? $quote->status);
        $this->assertNotNull($quote->sent_at);
        $this->assertNotNull($quote->approved_at);
        $this->assertSame($createdAt->toDateTimeString(), $quote->sent_at?->toDateTimeString());
        $this->assertSame($approvedAt->toDateTimeString(), $quote->approved_at?->toDateTimeString());
    }
}
