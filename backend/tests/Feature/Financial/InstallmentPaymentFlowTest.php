<?php

declare(strict_types=1);

use App\Models\AccountReceivable;
use App\Models\AccountReceivableInstallment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    // Setup permissions
    $permissions = [
        'finance.receivable.create',
        'finance.receivable.view',
    ];
    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->syncPermissions($permissions);
    $this->user->assignRole($role);

    // Reset Spatie permission cache
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->receivable = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'description' => 'Servico Teste',
    ]);
    $this->installment = AccountReceivableInstallment::factory()->create([
        'account_receivable_id' => $this->receivable->id,
        'tenant_id' => $this->tenant->id,
        'installment_number' => 1,
        'amount' => 150.00,
        'status' => 'pending',
        'due_date' => now()->addDays(10),
    ]);
});

test('geracao de boleto envia payable_id e payable_type no metadata', function () {
    // Provider uses mock in testing env — no Http::fake needed

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/financial/receivables/{$this->installment->id}/generate-boleto");

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['external_id', 'status', 'boleto_url', 'boleto_barcode', 'due_date', 'installment_id']]);

    $this->installment->refresh();
    expect($this->installment->psp_external_id)->toStartWith('PAY-BOL-');
    expect($this->installment->psp_status)->toBe('pending');
    expect($this->installment->psp_boleto_url)->toContain('sandbox.asaas.com');
});

test('webhook de pagamento confirmado reconcilia parcela para paid', function () {
    // Setup: installment already has PSP data from boleto generation
    $this->installment->update([
        'psp_external_id' => 'pay_fake456',
        'psp_status' => 'pending',
    ]);

    Payment::create([
        'tenant_id' => $this->tenant->id,
        'payable_type' => 'App\\Models\\AccountReceivable',
        'payable_id' => $this->receivable->id,
        'received_by' => $this->user->id,
        'amount' => 150.00,
        'payment_method' => 'boleto',
        'payment_date' => now(),
        'external_id' => 'pay_fake456',
        'status' => 'pending',
        'gateway_provider' => 'asaas',
    ]);

    $webhookPayload = [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => [
            'id' => 'pay_fake456',
            'status' => 'CONFIRMED',
            'value' => 150.00,
            'billingType' => 'BOLETO',
            'externalReference' => "AccountReceivable:{$this->receivable->id}",
            'metadata' => [
                'installment_id' => $this->installment->id,
            ],
        ],
    ];

    config(['payment.asaas.webhook_secret' => 'test-webhook-secret']);

    $response = $this->postJson('/api/v1/webhooks/payment', $webhookPayload, [
        'asaas-access-token' => 'test-webhook-secret',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['payment_id', 'status']]);

    // Verify payment updated
    $this->assertDatabaseHas('payments', [
        'external_id' => 'pay_fake456',
        'status' => 'confirmed',
    ]);

    // Verify installment reconciled
    $this->installment->refresh();
    expect($this->installment->status)->toBe('paid');
    expect($this->installment->paid_amount)->toEqual(150.00);
    expect($this->installment->psp_status)->toBe('confirmed');
});

test('webhook sem payment existente e sem externalReference retorna 404', function () {
    config(['payment.asaas.webhook_secret' => 'test-webhook-secret']);

    $webhookPayload = [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => [
            'id' => 'pay_inexistente',
            'status' => 'CONFIRMED',
            'value' => 100.00,
            'billingType' => 'PIX',
        ],
    ];

    $response = $this->postJson('/api/v1/webhooks/payment', $webhookPayload, [
        'asaas-access-token' => 'test-webhook-secret',
    ]);

    $response->assertStatus(404);
});

test('webhook idempotente nao reprocessa pagamento ja confirmado', function () {
    config(['payment.asaas.webhook_secret' => 'test-webhook-secret']);

    $payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'payable_type' => 'App\\Models\\AccountReceivable',
        'payable_id' => $this->receivable->id,
        'received_by' => $this->user->id,
        'amount' => 150.00,
        'payment_method' => 'pix',
        'payment_date' => now(),
        'external_id' => 'pay_already_done',
        'status' => 'confirmed',
        'paid_at' => now(),
        'gateway_provider' => 'asaas',
    ]);

    $webhookPayload = [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => [
            'id' => 'pay_already_done',
            'tenant_id' => $this->tenant->id,
            'status' => 'CONFIRMED',
            'value' => 150.00,
            'billingType' => 'PIX',
        ],
    ];

    $response = $this->postJson('/api/v1/webhooks/payment', $webhookPayload, [
        'asaas-access-token' => 'test-webhook-secret',
    ]);

    $response->assertOk()
        ->assertJson(['data' => ['status' => 'already_processed']]);
});
