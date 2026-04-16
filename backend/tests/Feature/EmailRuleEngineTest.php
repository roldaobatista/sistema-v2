<?php

namespace Tests\Feature;

use App\Enums\ServiceCallStatus;
use App\Models\Customer;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailRule;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Email\EmailRuleEngine;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailRuleEngineTest extends TestCase
{
    public function test_create_chamado_action_creates_valid_service_call(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Email',
        ]);

        $account = EmailAccount::create([
            'tenant_id' => $tenant->id,
            'label' => 'Inbox',
            'email_address' => 'inbox@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'inbox@example.com',
            'imap_password' => 'secret',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);

        $email = Email::create([
            'tenant_id' => $tenant->id,
            'email_account_id' => $account->id,
            'customer_id' => $customer->id,
            'assigned_to_user_id' => $user->id,
            'message_id' => '<email-rule@example.com>',
            'thread_id' => 'thread-email-rule',
            'folder' => 'INBOX',
            'from_address' => 'cliente@example.com',
            'from_name' => 'Cliente Email',
            'to_addresses' => [['email' => 'inbox@example.com']],
            'subject' => 'Equipamento parado',
            'body_text' => 'Balanca sem funcionar desde ontem.',
            'ai_summary' => 'Cliente relata parada do equipamento.',
            'ai_priority' => 'alta',
            'date' => now(),
            'direction' => 'inbound',
            'status' => 'new',
        ]);

        EmailRule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Criar chamado suporte',
            'is_active' => true,
            'priority' => 10,
            'conditions' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'Equipamento'],
            ],
            'actions' => [
                ['type' => 'create_chamado', 'params' => []],
            ],
        ]);

        $result = app(EmailRuleEngine::class)->apply($email);

        $this->assertCount(1, $result);

        $serviceCall = ServiceCall::query()->where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($serviceCall);
        $this->assertSame(ServiceCallStatus::PENDING_SCHEDULING->value, $serviceCall->status->value);
        $this->assertSame('high', $serviceCall->priority);
        if (Schema::hasColumn('service_calls', 'source')) {
            $this->assertSame('email', $serviceCall->source);
        }
        $this->assertSame($customer->id, $serviceCall->customer_id);
        $this->assertSame($user->id, $serviceCall->created_by);
        $this->assertStringContainsString('Equipamento parado', (string) $serviceCall->observations);
        $this->assertStringContainsString('Cliente relata parada do equipamento.', (string) $serviceCall->observations);

        $email->refresh();
        $this->assertSame(ServiceCall::class, $email->linked_type);
        $this->assertSame($serviceCall->id, $email->linked_id);
    }
}
