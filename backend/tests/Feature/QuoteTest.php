<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Jobs\SendQuoteEmailJob;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteEmail;
use App\Models\QuoteTag;
use App\Models\QuoteTemplate;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Equipment $equipment;

    private Product $product;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
        Event::fake();
        Gate::before(fn () => true);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function makeQuoteWithItems(array $quoteAttrs = []): Quote
    {
        $quote = Quote::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ], $quoteAttrs));

        $eq = $quote->equipments()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'description' => 'Manutenção',
            'sort_order' => 0,
        ]);

        $eq->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 1,
            'original_price' => 500,
            'unit_price' => 500,
            'discount_percentage' => 0,
            'sort_order' => 0,
        ]);

        return $quote;
    }

    // ── Testes Existentes (mantidos) ────────────────────────────────────

    public function test_create_quote(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addDays(7)->format('Y-m-d'),
            'discount_percentage' => 0,
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'description' => 'Manutencao preventiva',
                    'items' => [
                        [
                            'type' => 'product',
                            'product_id' => $this->product->id,
                            'quantity' => 1,
                            'original_price' => 100,
                            'unit_price' => 100,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/quotes', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', Quote::STATUS_DRAFT);
    }

    public function test_create_quote_accepts_minimal_draft_payload_without_equipments(): void
    {
        $response = $this->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'title' => 'Orcamento legado',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', Quote::STATUS_DRAFT)
            ->assertJsonPath('data.total', '0.00')
            ->assertJsonPath('data.customer_id', $this->customer->id);

        $this->assertDatabaseHas('quotes', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'observations' => 'Orcamento legado',
        ]);
    }

    public function test_list_quotes(): void
    {
        Quote::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson('/api/v1/quotes');

        $response->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_list_quotes_can_filter_by_tag(): void
    {
        $tag = QuoteTag::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Urgente',
            'color' => '#EF4444',
        ]);

        $taggedQuote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_number' => 'ORC-TAGGED',
        ]);
        $taggedQuote->tags()->attach($tag->id);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_number' => 'ORC-UNTAGGED',
        ]);

        $response = $this->getJson("/api/v1/quotes?tag_id={$tag->id}");

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.quote_number', 'ORC-TAGGED')
            ->assertJsonPath('data.0.tags.0.name', 'Urgente');
    }

    public function test_list_quotes_validates_filters(): void
    {
        $this->getJson('/api/v1/quotes?status=invalid&per_page=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'per_page']);
    }

    public function test_show_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $tag = QuoteTag::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'VIP',
            'color' => '#3B82F6',
        ]);
        $quote->tags()->attach($tag->id);

        $response = $this->getJson("/api/v1/quotes/{$quote->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $quote->id)
            ->assertJsonPath('data.tags.0.name', 'VIP')
            ->assertJsonPath('data.approval_url', $quote->approval_url)
            ->assertJsonPath('data.pdf_url', $quote->pdf_url);
    }

    public function test_approve_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);

        $response->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_APPROVED);
    }

    public function test_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();

        Quote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'quote_number' => 'EXT-001',
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_number' => 'INT-001',
        ]);

        $response = $this->getJson('/api/v1/quotes');

        $response->assertOk()
            ->assertSee('INT-001')
            ->assertDontSee('EXT-001');
    }

    public function test_quote_sequence_start_setting_is_respected_without_breaking_existing_sequence(): void
    {
        SystemSetting::setValue('quote_sequence_start', '1500', 'integer', 'quotes');

        $this->assertSame('ORC-01500', Quote::nextNumber($this->tenant->id));

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_number' => 'ORC-01620',
        ]);

        $this->assertSame('ORC-01621', Quote::nextNumber($this->tenant->id));

        SystemSetting::setValue('quote_sequence_start', '2000', 'integer', 'quotes');

        $this->assertSame('ORC-02000', Quote::nextNumber($this->tenant->id));
    }

    public function test_quote_sequence_uses_highest_historical_value_even_if_latest_record_is_external(): void
    {
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_number' => 'ORC-03000',
        ]);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_number' => 'EXT-00001',
        ]);

        $this->assertSame('ORC-03001', Quote::nextNumber($this->tenant->id));
    }

    public function test_create_quote_uses_sequence_start_from_settings_endpoint(): void
    {
        $this->putJson('/api/v1/settings', [
            'settings' => [
                [
                    'key' => 'quote_sequence_start',
                    'value' => '3500',
                    'type' => 'integer',
                    'group' => 'quotes',
                ],
            ],
        ])->assertOk();

        $payload = [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'description' => 'Orcamento teste',
                    'items' => [
                        [
                            'type' => 'product',
                            'product_id' => $this->product->id,
                            'quantity' => 1,
                            'original_price' => 100,
                            'unit_price' => 100,
                            'discount_percentage' => 0,
                        ],
                    ],
                ],
            ],
        ];

        $first = $this->postJson('/api/v1/quotes', $payload);
        $first->assertStatus(201)
            ->assertJsonPath('data.quote_number', 'ORC-03500');

        $second = $this->postJson('/api/v1/quotes', $payload);
        $second->assertStatus(201)
            ->assertJsonPath('data.quote_number', 'ORC-03501');
    }

    public function test_rejects_invalid_quote_sequence_start_setting(): void
    {
        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                [
                    'key' => 'quote_sequence_start',
                    'value' => '0',
                    'type' => 'integer',
                    'group' => 'quotes',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.value']);
    }

    public function test_cannot_mutate_sent_quote_through_item_and_equipment_endpoints(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $quoteEquipment = $quote->equipments()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'description' => 'Equipamento bloqueado para edicao',
            'sort_order' => 0,
        ]);

        $item = $quoteEquipment->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 1,
            'original_price' => 100,
            'unit_price' => 100,
            'discount_percentage' => 0,
            'sort_order' => 0,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/equipments", [
            'equipment_id' => $this->equipment->id,
        ])->assertStatus(422);

        $this->postJson("/api/v1/quote-equipments/{$quoteEquipment->id}/items", [
            'type' => 'product',
            'product_id' => $this->product->id,
            'quantity' => 1,
            'original_price' => 50,
            'unit_price' => 50,
        ])->assertStatus(422);

        $this->deleteJson("/api/v1/quote-items/{$item->id}")
            ->assertStatus(422);

        $this->deleteJson("/api/v1/quotes/{$quote->id}/equipments/{$quoteEquipment->id}")
            ->assertStatus(422);
    }

    public function test_convert_to_os_conflict_returns_business_number(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_APPROVED,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
            'os_number' => 'BL-OS-7711',
            'number' => 'OS-000771',
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os");

        $response->assertStatus(409)
            ->assertJsonPath('data.work_order.os_number', 'BL-OS-7711')
            ->assertJsonPath('data.work_order.business_number', 'BL-OS-7711');
    }

    public function test_convert_to_os_treats_string_false_as_false_for_installation_testing(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_APPROVED,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os", [
            'is_installation_testing' => 'false',
        ])->assertCreated();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_IN_EXECUTION,
            'is_installation_testing' => 0,
        ]);
    }

    public function test_convert_to_chamado_treats_string_false_as_false_for_installation_testing(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_APPROVED,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-chamado", [
            'is_installation_testing' => 'false',
        ])->assertCreated();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_IN_EXECUTION,
            'is_installation_testing' => 0,
        ]);
    }

    public function test_reject_quote_with_reason(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/reject", [
            'reason' => 'Preço muito alto',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_REJECTED)
            ->assertJsonPath('data.rejection_reason', 'Preço muito alto');

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_REJECTED,
            'rejection_reason' => 'Preço muito alto',
        ]);
    }

    public function test_can_update_pending_internal_quote(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_PENDING_INTERNAL,
            'observations' => 'Antes da edicao',
        ]);

        $this->putJson("/api/v1/quotes/{$quote->id}", [
            'observations' => 'Depois da edicao',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_PENDING_INTERNAL)
            ->assertJsonPath('data.observations', 'Depois da edicao');

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_PENDING_INTERNAL,
            'observations' => 'Depois da edicao',
        ]);
    }

    public function test_can_update_renegotiation_quote(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_RENEGOTIATION,
            'internal_notes' => 'Nota antiga',
        ]);

        $this->putJson("/api/v1/quotes/{$quote->id}", [
            'internal_notes' => 'Nota ajustada',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_RENEGOTIATION)
            ->assertJsonPath('data.internal_notes', 'Nota ajustada');

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_RENEGOTIATION,
            'internal_notes' => 'Nota ajustada',
        ]);
    }

    public function test_reject_quote_validates_reason_type(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/reject", [
            'reason' => ['invalid'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_cannot_reject_draft_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/reject")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Orçamento precisa estar enviado para rejeitar');
    }

    // ── Novos Testes Profundos ──────────────────────────────────────────

    // Q-11: Cross-tenant show → 404
    public function test_cross_tenant_show_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherQuote = Quote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->getJson("/api/v1/quotes/{$otherQuote->id}")
            ->assertNotFound();
    }

    // Q-12: Validação de campos obrigatórios no POST /quotes
    public function test_store_validates_required_fields(): void
    {
        $this->postJson('/api/v1/quotes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'equipments']);
    }

    // Q-13: Persistência no banco ao criar
    public function test_create_quote_persists_to_database(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'description' => 'Serviço de calibração',
                    'items' => [
                        [
                            'type' => 'product',
                            'product_id' => $this->product->id,
                            'quantity' => 2,
                            'original_price' => 350,
                            'unit_price' => 350,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/quotes', $payload);
        $response->assertStatus(201);

        $quoteId = $response->json('data.id');
        $this->assertDatabaseHas('quotes', [
            'id' => $quoteId,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ]);
    }

    // Q-14: Fluxo completo de status: draft → pending_internal → internally_approved → sent → approved
    public function test_full_status_flow_draft_to_approved(): void
    {
        $quote = $this->makeQuoteWithItems();

        // DRAFT → PENDING_INTERNAL
        $this->postJson("/api/v1/quotes/{$quote->id}/request-internal-approval")
            ->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_PENDING_INTERNAL);

        // PENDING_INTERNAL → INTERNALLY_APPROVED (via internalApprove no controller)
        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_INTERNALLY_APPROVED);

        // INTERNALLY_APPROVED → SENT
        $this->postJson("/api/v1/quotes/{$quote->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_SENT);

        // SENT → APPROVED
        $this->postJson("/api/v1/quotes/{$quote->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_APPROVED);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_APPROVED,
        ]);
    }

    // Q-15: request-internal-approval sem itens → 422
    public function test_request_internal_approval_without_items_fails(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ]);
        // Sem equipment/items

        $this->postJson("/api/v1/quotes/{$quote->id}/request-internal-approval")
            ->assertUnprocessable();
    }

    // Q-16: Não pode enviar para o cliente sem passar por aprovação interna
    public function test_cannot_send_draft_quote_directly(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => Quote::STATUS_DRAFT]);

        $this->postJson("/api/v1/quotes/{$quote->id}/send")
            ->assertUnprocessable();
    }

    // Q-17: Destroy draft → 204 + soft-deleted
    public function test_destroy_draft_returns_204(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ]);

        $this->deleteJson("/api/v1/quotes/{$quote->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    // Q-18: Não pode excluir orçamento enviado (imutável)
    public function test_cannot_destroy_sent_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_SENT,
        ]);

        $this->deleteJson("/api/v1/quotes/{$quote->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => Quote::STATUS_SENT]);
    }

    // Q-19: Destroy aprovado vinculado a WO → 409
    public function test_cannot_destroy_quote_linked_to_work_order(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT, // mutable, mas tem WO vinculada
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
            'created_by' => $this->user->id,
        ]);

        $this->deleteJson("/api/v1/quotes/{$quote->id}")
            ->assertConflict();
    }

    // Q-20: Duplicar cria novo orçamento DRAFT com número diferente
    public function test_duplicate_creates_new_draft(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_REJECTED,
            'revision' => 4,
            'internal_approved_by' => $this->user->id,
            'internal_approved_at' => now()->subDays(4),
            'sent_at' => now()->subDays(3),
            'rejected_at' => now()->subDays(2),
            'rejection_reason' => 'Cliente pediu revisão comercial',
            'magic_token' => 'token-antigo-duplicacao',
            'client_ip_approval' => '127.0.0.1',
            'term_accepted_at' => now()->subDay(),
            'client_view_count' => 5,
            'followup_count' => 2,
            'is_installation_testing' => true,
            'approval_channel' => 'portal',
            'approval_notes' => 'Aguardando contraproposta',
            'approved_by_name' => 'Cliente Teste',
        ]);

        $originalNumber = $quote->quote_number;

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/duplicate");
        $response->assertStatus(201)
            ->assertJsonPath('data.status', Quote::STATUS_DRAFT)
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.rejection_reason', null);

        $newId = $response->json('data.id');

        $this->assertNotSame($originalNumber, $response->json('data.quote_number'));
        $this->assertDatabaseCount('quotes', 2);
        $this->assertDatabaseHas('quotes', [
            'id' => $newId,
            'status' => Quote::STATUS_DRAFT,
            'revision' => 1,
            'internal_approved_by' => null,
            'rejection_reason' => null,
            'magic_token' => null,
            'client_ip_approval' => null,
            'term_accepted_at' => null,
            'client_view_count' => 0,
            'followup_count' => 0,
            'is_installation_testing' => 0,
            'approval_channel' => null,
            'approval_notes' => null,
            'approved_by_name' => null,
        ]);
    }

    // Q-21: Reabrir orçamento rejeitado → draft
    public function test_reopen_rejected_quote_returns_to_draft(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_REJECTED,
            'rejection_reason' => 'Preço alto',
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_DRAFT);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_DRAFT,
            'rejection_reason' => null,
        ]);
    }

    // Q-22: Summary retorna estrutura esperada com contagens corretas
    public function test_summary_returns_expected_keys_and_counts(): void
    {
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ]);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_APPROVED,
        ]);

        // Orçamento de outro tenant não deve aparecer
        $otherTenant = Tenant::factory()->create();
        Quote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'status' => Quote::STATUS_DRAFT,
        ]);

        $this->getJson('/api/v1/quotes-summary')
            ->assertOk()
            ->assertJsonStructure(['data' => ['draft', 'pending_internal_approval', 'internally_approved', 'sent', 'approved', 'rejected', 'expired', 'in_execution', 'installation_testing', 'renegotiation', 'invoiced', 'total_month', 'conversion_rate']])
            ->assertJsonPath('data.draft', 1)
            ->assertJsonPath('data.approved', 1);
    }

    public function test_summary_is_scoped_for_restricted_roles(): void
    {
        $restrictedUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $otherSeller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $role = Role::findOrCreate('tecnico_vendedor', 'web');
        $restrictedUser->assignRole($role);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $restrictedUser->id,
            'status' => Quote::STATUS_DRAFT,
            'total' => 100,
        ]);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $otherSeller->id,
            'status' => Quote::STATUS_APPROVED,
            'total' => 999,
        ]);

        Sanctum::actingAs($restrictedUser, ['*']);

        $this->getJson('/api/v1/quotes-summary')
            ->assertOk()
            ->assertJsonPath('data.draft', 1)
            ->assertJsonPath('data.approved', 0)
            ->assertJsonPath('data.total_month', 100);
    }

    public function test_advanced_summary_is_scoped_for_restricted_roles(): void
    {
        $restrictedUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $otherSeller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $role = Role::findOrCreate('tecnico_vendedor', 'web');
        $restrictedUser->assignRole($role);

        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $restrictedUser->id,
            'status' => Quote::STATUS_APPROVED,
            'total' => 150,
        ]);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $otherSeller->id,
            'status' => Quote::STATUS_APPROVED,
            'total' => 999,
        ]);

        Sanctum::actingAs($restrictedUser, ['*']);

        $this->getJson('/api/v1/quotes-advanced-summary')
            ->assertOk()
            ->assertJsonPath('data.total_quotes', 1)
            ->assertJsonPath('data.total_approved', 1)
            ->assertJsonCount(1, 'data.top_sellers')
            ->assertJsonPath('data.top_sellers.0.seller_id', $restrictedUser->id)
            ->assertJsonPath('data.top_sellers.0.total_value', 150);
    }

    // Q-23: Não pode rejeitar orçamento aprovado (deve estar SENT)
    public function test_cannot_reject_approved_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_APPROVED,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/reject", ['reason' => 'Tarde demais'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Orçamento precisa estar enviado para rejeitar');
    }

    // Q-24: Aprovação interna requer status pending_internal_approval (não aceita mais draft direto)
    public function test_internal_approve_from_draft_is_blocked(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertUnprocessable();
    }

    // Q-24b: Fluxo correto: pending_internal_approval → internally_approved
    public function test_internal_approve_from_pending_succeeds(): void
    {
        $quote = $this->makeQuoteWithItems(['status' => QuoteStatus::PENDING_INTERNAL_APPROVAL->value]);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve");

        $response->assertOk()
            ->assertJsonPath('data.status', QuoteStatus::INTERNALLY_APPROVED->value);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_INTERNALLY_APPROVED,
            'internal_approved_by' => $this->user->id,
        ]);
    }

    // Q-25: Aprovação interna não permitida para orçamento já aprovado pelo cliente
    public function test_internal_approve_blocked_for_approved_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_APPROVED,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")
            ->assertUnprocessable();
    }

    // Q-26 (BUG FIX): createFromTemplate rejeita customer de outro tenant → 422
    public function test_create_from_template_rejects_cross_tenant_customer(): void
    {
        $template = QuoteTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template Padrão',
            'is_active' => true,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->postJson("/api/v1/quote-templates/{$template->id}/create-quote", [
            'customer_id' => $otherCustomer->id, // customer de outro tenant — deve ser rejeitado
            'equipments' => [
                ['equipment_id' => $this->equipment->id, 'items' => []],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    // Q-27: createFromTemplate com customer válido cria orçamento DRAFT
    public function test_create_from_template_with_valid_customer_creates_draft(): void
    {
        $template = QuoteTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template Padrão',
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/quote-templates/{$template->id}/create-quote", [
            'customer_id' => $this->customer->id,
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'items' => [],
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('quotes', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_DRAFT,
        ]);
    }

    public function test_quote_pdf_renders_when_payment_terms_is_plain_string(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'payment_terms' => 'boleto_28_dias',
            'payment_terms_detail' => '28 dias apos a emissao',
        ]);

        $response = $this->get("/api/v1/quotes/{$quote->id}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_add_item_allows_service_without_lazy_loading_failure(): void
    {
        Model::preventLazyLoading(true);

        try {
            $quote = Quote::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'status' => Quote::STATUS_DRAFT,
            ]);

            $quoteEquipment = $quote->equipments()->create([
                'tenant_id' => $this->tenant->id,
                'equipment_id' => $this->equipment->id,
                'description' => 'Equipamento com servico',
                'sort_order' => 0,
            ]);

            $response = $this->postJson("/api/v1/quote-equipments/{$quoteEquipment->id}/items", [
                'type' => 'service',
                'service_id' => $this->service->id,
                'quantity' => 1,
                'original_price' => 120,
                'unit_price' => 120,
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.type', 'service')
                ->assertJsonPath('data.service_id', $this->service->id)
                ->assertJsonPath('data.description', $this->service->name)
                ->assertJsonMissingPath('data.quote_equipment');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_quote_pdf_renders_with_service_item_description(): void
    {
        Model::preventLazyLoading(true);

        try {
            $quote = Quote::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'seller_id' => $this->user->id,
                'status' => Quote::STATUS_DRAFT,
            ]);

            $quoteEquipment = $quote->equipments()->create([
                'tenant_id' => $this->tenant->id,
                'equipment_id' => $this->equipment->id,
                'description' => 'Equipamento com servico em PDF',
                'sort_order' => 0,
            ]);

            $quoteEquipment->items()->create([
                'tenant_id' => $this->tenant->id,
                'type' => 'service',
                'service_id' => $this->service->id,
                'quantity' => 1,
                'original_price' => 250,
                'unit_price' => 250,
                'discount_percentage' => 0,
                'sort_order' => 0,
            ]);

            $response = $this->get("/api/v1/quotes/{$quote->id}/pdf");

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_quote_pdf_renders_with_long_company_and_customer_metadata(): void
    {
        Model::preventLazyLoading(true);

        try {
            $this->tenant->forceFill([
                'name' => 'SOLUTION AUTOMACAO E PESAGEM LTDA COMERCIAL INDUSTRIAL DE SISTEMAS DE PESAGEM E AUTOMACAO',
                'email' => 'contato.comercial@example.test.br',
                'phone' => '(66) 9235-6105',
                'address_street' => 'Avenida Industrial de Integracao Tecnologica',
                'address_number' => '1450',
                'address_neighborhood' => 'Distrito Empresarial',
                'address_city' => 'Cuiaba',
                'address_state' => 'MT',
            ])->save();

            $this->customer->update([
                'name' => 'ZOOTEC INDUSTRIA E COMERCIO DE PRODUTOS AGROPECUARIOS LTDA UNIDADE MATRIZ',
                'email' => 'contabilidade.financeiro@grupozootec.com.br',
                'phone' => '(66) 3411-5900',
                'address_street' => 'Rodovia BR-364',
                'address_number' => 'KM 12',
                'address_neighborhood' => 'Zona Rural',
                'address_city' => 'Rondonopolis',
                'address_state' => 'MT',
            ]);

            $quote = $this->makeQuoteWithItems([
                'seller_id' => $this->user->id,
                'status' => Quote::STATUS_SENT,
                'observations' => 'Executar o servico com alinhamento previo de agenda, janela operacional e validacao do responsavel local para evitar retrabalho.',
                'payment_terms' => 'personalizado',
                'payment_terms_detail' => '153045-dias',
            ]);

            $response = $this->get("/api/v1/quotes/{$quote->id}/pdf");

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_update_quote_returns_service_items_without_lazy_loading_failure(): void
    {
        Model::preventLazyLoading(true);

        try {
            $quote = Quote::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'seller_id' => $this->user->id,
                'status' => Quote::STATUS_DRAFT,
            ]);

            $quoteEquipment = $quote->equipments()->create([
                'tenant_id' => $this->tenant->id,
                'equipment_id' => $this->equipment->id,
                'description' => 'Equipamento com servico para update',
                'sort_order' => 0,
            ]);

            $quoteEquipment->items()->create([
                'tenant_id' => $this->tenant->id,
                'type' => 'service',
                'service_id' => $this->service->id,
                'quantity' => 1,
                'original_price' => 180,
                'unit_price' => 180,
                'discount_percentage' => 0,
                'sort_order' => 0,
            ]);

            $response = $this->putJson("/api/v1/quotes/{$quote->id}", [
                'observations' => 'Orçamento atualizado com item de serviço',
            ]);

            $response->assertOk()
                ->assertJsonPath('data.observations', 'Orçamento atualizado com item de serviço')
                ->assertJsonPath('data.equipments.0.items.0.description', $this->service->name);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_request_internal_approval_returns_service_items_without_lazy_loading_failure(): void
    {
        Model::preventLazyLoading(true);

        try {
            $quote = Quote::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'seller_id' => $this->user->id,
                'status' => Quote::STATUS_DRAFT,
            ]);

            $quoteEquipment = $quote->equipments()->create([
                'tenant_id' => $this->tenant->id,
                'equipment_id' => $this->equipment->id,
                'description' => 'Equipamento aguardando aprovacao',
                'sort_order' => 0,
            ]);

            $quoteEquipment->items()->create([
                'tenant_id' => $this->tenant->id,
                'type' => 'service',
                'service_id' => $this->service->id,
                'quantity' => 1,
                'original_price' => 210,
                'unit_price' => 210,
                'discount_percentage' => 0,
                'sort_order' => 0,
            ]);

            $response = $this->postJson("/api/v1/quotes/{$quote->id}/request-internal-approval");

            $response->assertOk()
                ->assertJsonPath('data.status', Quote::STATUS_PENDING_INTERNAL)
                ->assertJsonPath('data.equipments.0.items.0.description', $this->service->name);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_internal_approve_returns_service_items_without_lazy_loading_failure(): void
    {
        Model::preventLazyLoading(true);

        try {
            $quote = Quote::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'seller_id' => $this->user->id,
                'status' => Quote::STATUS_PENDING_INTERNAL,
            ]);

            $quoteEquipment = $quote->equipments()->create([
                'tenant_id' => $this->tenant->id,
                'equipment_id' => $this->equipment->id,
                'description' => 'Equipamento para aprovacao interna',
                'sort_order' => 0,
            ]);

            $quoteEquipment->items()->create([
                'tenant_id' => $this->tenant->id,
                'type' => 'service',
                'service_id' => $this->service->id,
                'quantity' => 1,
                'original_price' => 310,
                'unit_price' => 310,
                'discount_percentage' => 0,
                'sort_order' => 0,
            ]);

            $response = $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve");

            $response->assertOk()
                ->assertJsonPath('data.status', Quote::STATUS_INTERNALLY_APPROVED)
                ->assertJsonPath('data.equipments.0.items.0.description', $this->service->name);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_quote_pdf_view_formats_payment_schedule_professionally(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'created_at' => now()->startOfDay(),
            'payment_terms' => 'personalizado',
            'payment_terms_detail' => '153045-dias',
        ]);

        $quote->load([
            'customer',
            'seller',
            'equipments.equipment',
            'equipments.items.product',
            'equipments.items.service',
        ]);

        $html = view('pdf.quote', [
            'quote' => $quote,
            'tenant' => $this->tenant,
            'company_logo_path' => null,
            'company_tagline' => null,
        ])->render();

        $normalizedHtml = Str::of($html)->ascii()->value();

        $this->assertStringContainsString('Condicoes de Pagamento', $normalizedHtml);
        $this->assertStringContainsString('<td class="payment-summary-label">Meio</td>', $normalizedHtml);
        $this->assertStringContainsString('<td class="payment-summary-value">A combinar</td>', $normalizedHtml);
        $this->assertStringContainsString('Pagamento em 3 parcelas com vencimentos programados apos a emissao.', $normalizedHtml);
        $this->assertStringContainsString('Programacao de vencimentos', $normalizedHtml);
        $this->assertStringContainsString('1a parcela', $normalizedHtml);
        $this->assertStringContainsString('15 dias apos emissao', $normalizedHtml);
        $this->assertStringContainsString('30 dias apos emissao', $normalizedHtml);
        $this->assertStringContainsString('45 dias apos emissao', $normalizedHtml);
    }

    public function test_public_view_tracks_client_visualization_metadata(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'client_view_count' => 0,
            'client_viewed_at' => null,
        ]);

        $response = $this->getJson("/api/v1/quotes/{$quote->id}/public-view?token={$quote->approval_token}");

        $response->assertOk()
            ->assertJsonPath('data.id', $quote->id);

        $quote->refresh();

        $this->assertSame(1, $quote->client_view_count);
        $this->assertNotNull($quote->client_viewed_at);
    }

    public function test_public_view_returns_sanitized_contract_without_internal_fields(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'internal_notes' => 'Margem interna sensível',
            'client_ip_approval' => '10.0.0.1',
            'magic_token' => str_repeat('v', 64),
        ]);

        $response = $this->getJson("/api/v1/quotes/{$quote->id}/public-view?token={$quote->magic_token}");

        $response->assertOk()
            ->assertJsonPath('data.id', $quote->id)
            ->assertJsonPath('data.customer.name', $this->customer->name);

        $payload = $response->json('data');

        foreach ([
            'tenant_id',
            'customer_id',
            'internal_notes',
            'client_ip_approval',
            'approval_token',
            'approval_url',
            'magic_token',
        ] as $sensitiveKey) {
            $this->assertArrayNotHasKey($sensitiveKey, $payload);
        }
    }

    public function test_public_pdf_accepts_magic_token_when_present(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('m', 64),
        ]);

        $this->get("/api/v1/quotes/{$quote->id}/public-pdf?token={$quote->magic_token}")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_whatsapp_link_requires_quote_to_be_sent_to_customer(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_INTERNALLY_APPROVED,
            'magic_token' => null,
        ]);

        $this->getJson("/api/v1/quotes/{$quote->id}/whatsapp")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Orçamento precisa ser enviado ao cliente antes de compartilhar link, WhatsApp ou e-mail.');
    }

    public function test_whatsapp_link_normalizes_brazilian_phone_with_area_code(): void
    {
        config()->set('app.frontend_url', 'http://localhost:3000');
        config()->set('app.url', 'http://203.0.113.10');

        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('w', 64),
        ]);

        $quote->customer()->update([
            'phone' => '(66) 99235-6105',
        ]);

        $response = $this
            ->withServerVariables([
                'HTTP_HOST' => 'app.example.test',
                'HTTPS' => 'on',
            ])
            ->getJson("/api/v1/quotes/{$quote->id}/whatsapp");

        $response->assertOk()
            ->assertJsonPath('data.phone', '5566992356105')
            ->assertJsonPath('data.url', fn (string $url) => str_contains($url, 'https://wa.me/5566992356105?text='));

        $url = (string) $response->json('data.url');
        $query = parse_url($url, PHP_URL_QUERY);

        parse_str((string) $query, $params);

        $decodedText = urldecode((string) ($params['text'] ?? ''));

        $this->assertStringContainsString('Olá,', $decodedText);
        $this->assertStringContainsString("proposta comercial {$quote->quote_number}", $decodedText);
        $this->assertStringContainsString('Para visualizar e aprovar online, acesse:', $decodedText);
        $this->assertStringContainsString('O PDF da proposta está disponível em:', $decodedText);
        $this->assertStringContainsString('Permanecemos à disposição para qualquer esclarecimento.', $decodedText);
        $this->assertStringContainsString('/api/v1/quotes/'.$quote->id.'/public-pdf?token=', $decodedText);
        $this->assertStringContainsString('/quotes/proposal/', $decodedText);
        $this->assertStringNotContainsString('localhost', $decodedText);
    }

    public function test_whatsapp_link_uses_centralized_public_urls_when_frontend_and_api_domains_differ(): void
    {
        config()->set('app.frontend_url', 'http://localhost:3000');
        config()->set('app.url', 'http://localhost');
        config()->set('app.public_frontend_url', 'https://portal.example.com');
        config()->set('app.public_app_url', 'https://api.example.com');

        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('p', 64),
        ]);

        $quote->customer()->update([
            'phone' => '(66) 99235-6105',
        ]);

        $response = $this->getJson("/api/v1/quotes/{$quote->id}/whatsapp");

        $response->assertOk();

        $url = (string) $response->json('data.url');
        $query = parse_url($url, PHP_URL_QUERY);

        parse_str((string) $query, $params);

        $decodedText = urldecode((string) ($params['text'] ?? ''));

        $this->assertStringContainsString($quote->approval_url, $decodedText);
        $this->assertStringContainsString($quote->pdf_url, $decodedText);
        $this->assertStringContainsString('https://portal.example.com/quotes/proposal/', $decodedText);
        $this->assertStringContainsString('https://api.example.com/api/v1/quotes/', $decodedText);
        $this->assertStringNotContainsString('http://localhost', $decodedText);
    }

    public function test_send_email_requires_quote_to_be_sent_to_customer(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_INTERNALLY_APPROVED,
            'magic_token' => null,
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/email", [
            'recipient_email' => 'cliente@example.com',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Orçamento precisa ser enviado ao cliente antes de compartilhar link, WhatsApp ou e-mail.');
    }

    public function test_send_email_queues_job_when_quote_is_sent(): void
    {
        Queue::fake();

        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('e', 64),
        ]);

        $this->postJson("/api/v1/quotes/{$quote->id}/email", [
            'recipient_email' => 'cliente@example.com',
            'recipient_name' => 'Cliente Teste',
            'message' => 'Segue para sua analise.',
        ])->assertCreated()
            ->assertJsonPath('data.recipient_email', 'cliente@example.com')
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(SendQuoteEmailJob::class);

        $emailLog = QuoteEmail::query()->where('quote_id', $quote->id)->latest('id')->first();

        $this->assertNotNull($emailLog);
        $this->assertNotNull($emailLog->queued_at);
        $this->assertNull($emailLog->sent_at);
        $this->assertNull($emailLog->failed_at);
        $this->assertNull($emailLog->error_message);
    }

    public function test_send_quote_email_job_failed_persists_failure_details(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'magic_token' => str_repeat('f', 64),
        ]);

        $emailLog = QuoteEmail::create([
            'tenant_id' => $quote->tenant_id,
            'quote_id' => $quote->id,
            'sent_by' => $this->user->id,
            'recipient_email' => 'cliente@example.com',
            'recipient_name' => 'Cliente Teste',
            'subject' => "Orçamento #{$quote->quote_number}",
            'status' => 'queued',
            'queued_at' => now(),
            'pdf_attached' => true,
        ]);

        $job = new SendQuoteEmailJob(
            $quote->id,
            'cliente@example.com',
            'Cliente Teste',
            'Mensagem de teste',
            $this->user->id,
            $emailLog->id,
        );

        $job->failed(new \RuntimeException('SMTP indisponivel'));

        $emailLog->refresh();

        $this->assertSame('failed', $emailLog->status);
        $this->assertNotNull($emailLog->failed_at);
        $this->assertSame('SMTP indisponivel', $emailLog->error_message);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $quote->tenant_id,
            'auditable_type' => Quote::class,
            'auditable_id' => $quote->id,
            'action' => 'email_failed',
        ]);
    }

    public function test_public_approve_legacy_token_persists_customer_approval_metadata(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'approved_at' => null,
            'term_accepted_at' => null,
            'client_ip_approval' => null,
        ]);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/public-approve?token={$quote->approval_token}");

        $response->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_APPROVED);

        $payload = $response->json('data');

        foreach ([
            'tenant_id',
            'customer_id',
            'internal_notes',
            'client_ip_approval',
            'approval_token',
            'approval_url',
            'magic_token',
        ] as $sensitiveKey) {
            $this->assertArrayNotHasKey($sensitiveKey, $payload);
        }

        $quote->refresh();

        $this->assertSame('public_token', $quote->approval_channel);
        $this->assertSame($this->customer->name, $quote->approved_by_name);
        $this->assertNotNull($quote->term_accepted_at);
        $this->assertSame('127.0.0.1', $quote->client_ip_approval);
    }

    public function test_quote_timeline_returns_action_label_and_user_name(): void
    {
        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
        ]);

        AuditLog::log('email_sent', "E-mail com orçamento {$quote->quote_number} enviado para cliente@example.com", $quote);

        $this->getJson("/api/v1/quotes/{$quote->id}/timeline")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'email_sent')
            ->assertJsonPath('data.0.action_label', 'E-mail Enviado')
            ->assertJsonPath('data.0.user_name', $this->user->name);
    }

    public function test_send_quote_generates_magic_token_and_public_frontend_link(): void
    {
        config()->set('app.frontend_url', 'https://frontend.example.com');

        $quote = $this->makeQuoteWithItems([
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_INTERNALLY_APPROVED,
            'magic_token' => null,
        ]);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/send");

        $response->assertOk()
            ->assertJsonPath('data.status', Quote::STATUS_SENT);

        $quote->refresh();

        $this->assertNotNull($quote->magic_token);
        $this->assertSame(
            "https://frontend.example.com/quotes/proposal/{$quote->magic_token}",
            $quote->approval_url
        );
    }

    public function test_store_template_persists_supported_schema_fields(): void
    {
        $response = $this->postJson('/api/v1/quote-templates', [
            'name' => 'Template Comercial',
            'warranty_terms' => 'Garantia de 12 meses',
            'payment_terms_text' => '50% entrada e 50% entrega',
            'general_conditions' => 'Conforme escopo aprovado.',
            'delivery_terms' => 'Entrega em ate 10 dias.',
            'is_default' => true,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.warranty_terms', 'Garantia de 12 meses')
            ->assertJsonPath('data.payment_terms_text', '50% entrada e 50% entrega')
            ->assertJsonPath('data.general_conditions', 'Conforme escopo aprovado.')
            ->assertJsonPath('data.delivery_terms', 'Entrega em ate 10 dias.')
            ->assertJsonPath('data.is_default', true);
    }

    public function test_create_quote_accepts_template_id_from_same_tenant(): void
    {
        $template = QuoteTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template Base',
            'payment_terms_text' => '30 dias',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'template_id' => $template->id,
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'description' => 'Servico com template',
                    'items' => [
                        [
                            'type' => 'product',
                            'product_id' => $this->product->id,
                            'quantity' => 1,
                            'original_price' => 100,
                            'unit_price' => 100,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('quotes', [
            'customer_id' => $this->customer->id,
            'template_id' => $template->id,
        ]);
    }

    public function test_create_quote_rejects_cross_tenant_template_id(): void
    {
        $otherTenant = Tenant::factory()->create();
        $template = QuoteTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Template Externo',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'template_id' => $template->id,
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'description' => 'Servico',
                    'items' => [
                        [
                            'type' => 'product',
                            'product_id' => $this->product->id,
                            'quantity' => 1,
                            'original_price' => 100,
                            'unit_price' => 100,
                        ],
                    ],
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_compare_quotes_returns_404_when_any_quote_is_outside_tenant(): void
    {
        $quoteA = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $quoteB = Quote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->postJson('/api/v1/quotes/compare', [
            'ids' => [$quoteA->id, $quoteB->id],
        ])->assertNotFound();
    }

    public function test_create_from_template_returns_404_for_cross_tenant_template(): void
    {
        $otherTenant = Tenant::factory()->create();
        $template = QuoteTemplate::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Template Externo',
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/quote-templates/{$template->id}/create-quote", [
            'customer_id' => $this->customer->id,
            'equipments' => [
                [
                    'equipment_id' => $this->equipment->id,
                    'items' => [],
                ],
            ],
        ])->assertNotFound();
    }
}
