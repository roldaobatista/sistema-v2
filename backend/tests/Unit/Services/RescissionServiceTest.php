<?php

namespace Tests\Unit\Services;

use App\Models\InssBracket;
use App\Models\IrrfBracket;
use App\Models\Rescission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RescissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RescissionServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private RescissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'salary' => 3000.00,
            'admission_date' => '2025-01-02',
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        InssBracket::firstOrCreate(['year' => 2026, 'min_salary' => 0], ['max_salary' => 1518.00, 'rate' => 7.50, 'deduction' => 0]);
        InssBracket::firstOrCreate(['year' => 2026, 'min_salary' => 1518.01], ['max_salary' => 2793.88, 'rate' => 9.00, 'deduction' => 0]);
        InssBracket::firstOrCreate(['year' => 2026, 'min_salary' => 2793.89], ['max_salary' => 4190.83, 'rate' => 12.00, 'deduction' => 0]);
        IrrfBracket::firstOrCreate(['year' => 2026, 'min_base' => 0], ['max_base' => 2259.20, 'rate' => 0, 'deduction' => 0]);
        IrrfBracket::firstOrCreate(['year' => 2026, 'min_base' => 2259.21], ['max_base' => 2826.65, 'rate' => 7.50, 'deduction' => 169.44]);

        $this->service = app(RescissionService::class);
    }

    public function test_sem_justa_causa_creates_rescission(): void
    {
        $rescission = $this->service->calculate($this->user, 'sem_justa_causa', Carbon::parse('2026-03-18'));

        $this->assertInstanceOf(Rescission::class, $rescission);
        $this->assertEquals('sem_justa_causa', $rescission->type);
        $this->assertTrue($rescission->exists);
    }

    public function test_sem_justa_causa_has_salary_balance_value(): void
    {
        $rescission = $this->service->calculate($this->user, 'sem_justa_causa', Carbon::parse('2026-03-18'));

        $this->assertGreaterThan(0, (float) $rescission->salary_balance_value);
    }

    public function test_sem_justa_causa_has_13th_proportional(): void
    {
        $rescission = $this->service->calculate($this->user, 'sem_justa_causa', Carbon::parse('2026-03-18'));

        $this->assertGreaterThan(0, (float) $rescission->thirteenth_proportional_value);
    }

    public function test_pedido_demissao_type(): void
    {
        $rescission = $this->service->calculate($this->user, 'pedido_demissao', Carbon::parse('2026-03-18'));

        $this->assertEquals('pedido_demissao', $rescission->type);
        $this->assertGreaterThan(0, (float) $rescission->salary_balance_value);
    }

    public function test_justa_causa_type(): void
    {
        $rescission = $this->service->calculate($this->user, 'justa_causa', Carbon::parse('2026-03-18'));

        $this->assertEquals('justa_causa', $rescission->type);
        $this->assertGreaterThan(0, (float) $rescission->salary_balance_value);
    }

    public function test_acordo_mutuo_type(): void
    {
        $rescission = $this->service->calculate($this->user, 'acordo_mutuo', Carbon::parse('2026-03-18'));

        $this->assertEquals('acordo_mutuo', $rescission->type);
        $this->assertGreaterThan(0, (float) $rescission->salary_balance_value);
    }

    public function test_rescission_is_persisted(): void
    {
        $rescission = $this->service->calculate($this->user, 'sem_justa_causa', Carbon::parse('2026-03-18'));

        $this->assertDatabaseHas('rescissions', [
            'id' => $rescission->id,
            'user_id' => $this->user->id,
            'type' => 'sem_justa_causa',
        ]);
    }

    public function test_generate_trct_html(): void
    {
        $rescission = $this->service->calculate($this->user, 'sem_justa_causa', Carbon::parse('2026-03-18'));
        $rescission->update(['status' => 'calculated']);

        $html = $this->service->generateTRCTHtml($rescission);

        $this->assertIsString($html);
        $this->assertStringContainsString($this->user->name, $html);
    }
}
