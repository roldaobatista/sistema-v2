<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateESocialEventsJob;
use App\Models\Payroll;
use App\Models\Tenant;
use App\Services\ESocialService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class GenerateESocialEventsJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_implements_should_queue_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $payroll = Payroll::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(ShouldQueue::class, new GenerateESocialEventsJob($payroll));
    }

    public function test_has_expected_retry_policy(): void
    {
        $tenant = Tenant::factory()->create();
        $payroll = Payroll::factory()->create(['tenant_id' => $tenant->id]);

        $job = new GenerateESocialEventsJob($payroll);

        $this->assertSame(3, $job->tries, 'Deve ter 3 tries para retry transiente de falhas eSocial');
        $this->assertSame(60, $job->backoff, 'Backoff de 60s entre tentativas');
    }

    public function test_dispatches_to_queue_with_payroll_bound(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $payroll = Payroll::factory()->create(['tenant_id' => $tenant->id]);

        GenerateESocialEventsJob::dispatch($payroll);

        Queue::assertPushed(
            GenerateESocialEventsJob::class,
            fn (GenerateESocialEventsJob $job) => $job->payroll->is($payroll)
        );
    }

    public function test_handle_delegates_to_service(): void
    {
        $tenant = Tenant::factory()->create();
        $payroll = Payroll::factory()->create(['tenant_id' => $tenant->id]);

        /** @var ESocialService&MockInterface $serviceMock */
        $serviceMock = Mockery::mock(ESocialService::class);
        $serviceMock->shouldReceive('generatePayrollEvents')
            ->once()
            ->with(Mockery::on(fn (Payroll $p) => $p->id === $payroll->id))
            ->andReturn([]);

        $job = new GenerateESocialEventsJob($payroll);
        $job->handle($serviceMock);

        // Mockery expectations sao validadas no tearDown()
        $this->addToAssertionCount(1);
    }

    public function test_failed_logs_without_throwing(): void
    {
        $tenant = Tenant::factory()->create();
        $payroll = Payroll::factory()->create(['tenant_id' => $tenant->id]);

        $job = new GenerateESocialEventsJob($payroll);

        // failed() apenas registra log — nao deve lancar exception nem crashar.
        $job->failed(new RuntimeException('erro simulado de transmissao eSocial'));

        $this->addToAssertionCount(1);
    }
}
