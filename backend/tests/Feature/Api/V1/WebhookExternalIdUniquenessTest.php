<?php

namespace Tests\Feature\Api\V1;

use App\Models\AccountReceivable;
use App\Models\CrmMessage;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class WebhookExternalIdUniquenessTest extends TestCase
{
    public function test_payment_external_id_is_globally_unique(): void
    {
        [$tenantA, $tenantB] = $this->createTenants();
        $receivableA = AccountReceivable::factory()->create(['tenant_id' => $tenantA->id]);
        $receivableB = AccountReceivable::factory()->create(['tenant_id' => $tenantB->id]);

        Payment::create([
            'tenant_id' => $tenantA->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivableA->id,
            'received_by' => User::factory()->create(['tenant_id' => $tenantA->id])->id,
            'amount' => 10,
            'payment_method' => 'pix',
            'payment_date' => now(),
            'external_id' => 'shared-payment-external-id',
            'status' => 'pending',
        ]);

        $this->expectException(QueryException::class);

        Payment::create([
            'tenant_id' => $tenantB->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivableB->id,
            'received_by' => User::factory()->create(['tenant_id' => $tenantB->id])->id,
            'amount' => 10,
            'payment_method' => 'pix',
            'payment_date' => now(),
            'external_id' => 'shared-payment-external-id',
            'status' => 'pending',
        ]);
    }

    public function test_crm_message_external_id_is_globally_unique(): void
    {
        [$tenantA, $tenantB] = $this->createTenants();
        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        CrmMessage::create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
            'channel' => CrmMessage::CHANNEL_WHATSAPP,
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Primeira mensagem',
            'external_id' => 'shared-crm-external-id',
        ]);

        $this->expectException(QueryException::class);

        CrmMessage::create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
            'channel' => CrmMessage::CHANNEL_WHATSAPP,
            'direction' => 'outbound',
            'status' => 'sent',
            'body' => 'Segunda mensagem',
            'external_id' => 'shared-crm-external-id',
        ]);
    }

    public function test_whatsapp_message_external_id_is_globally_unique(): void
    {
        [$tenantA, $tenantB] = $this->createTenants();

        WhatsappMessageLog::create([
            'tenant_id' => $tenantA->id,
            'direction' => 'outbound',
            'phone_to' => '5511999887766',
            'message' => 'Primeira mensagem',
            'message_type' => 'text',
            'status' => 'sent',
            'external_id' => 'shared-whatsapp-external-id',
        ]);

        $this->expectException(QueryException::class);

        WhatsappMessageLog::create([
            'tenant_id' => $tenantB->id,
            'direction' => 'outbound',
            'phone_to' => '5511999887767',
            'message' => 'Segunda mensagem',
            'message_type' => 'text',
            'status' => 'sent',
            'external_id' => 'shared-whatsapp-external-id',
        ]);
    }

    /**
     * @return array{Tenant, Tenant}
     */
    private function createTenants(): array
    {
        return [
            Tenant::factory()->create(),
            Tenant::factory()->create(),
        ];
    }
}
