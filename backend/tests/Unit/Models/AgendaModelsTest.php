<?php

namespace Tests\Unit\Models;

use App\Models\AgendaItem;
use App\Models\AgendaTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgendaModelsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── AgendaItem — Relationships ──

    public function test_agenda_item_belongs_to_responsavel(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $item->responsavel);
    }

    public function test_agenda_item_belongs_to_tenant(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->assertEquals($this->tenant->id, $item->tenant_id);
    }

    public function test_agenda_item_has_many_comments(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $item->comments());
    }

    public function test_agenda_item_has_many_subtasks(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $item->subtasks());
    }

    public function test_agenda_item_has_many_time_entries(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $item->timeEntries());
    }

    public function test_agenda_item_guarded_fields(): void
    {
        $item = new AgendaItem;
        $guarded = $item->getGuarded();

        $this->assertContains('id', $guarded);
        $this->assertCount(1, $guarded);
    }

    public function test_agenda_item_soft_delete(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
            'created_by_user_id' => $this->user->id,
        ]);

        $item->delete();

        $this->assertNull(AgendaItem::find($item->id));
        $this->assertNotNull(AgendaItem::withTrashed()->find($item->id));
    }

    public function test_agenda_item_date_casts(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
            'created_by_user_id' => $this->user->id,
            'due_at' => '2026-06-15 10:00:00',
        ]);

        $item->refresh();
        $this->assertNotNull($item->due_at);
    }

    // ── AgendaTemplate — Relationships ──

    public function test_agenda_template_belongs_to_tenant(): void
    {
        $template = AgendaTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template teste',
            'type' => 'task',
            'priority' => 'medium',
            'visibility' => 'team',
        ]);

        $this->assertEquals($this->tenant->id, $template->tenant_id);
    }

    public function test_agenda_template_guarded(): void
    {
        $template = new AgendaTemplate;
        $guarded = $template->getGuarded();

        $this->assertContains('id', $guarded);
        $this->assertCount(1, $guarded);
    }
}
