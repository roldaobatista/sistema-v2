<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessESocialBatchJob;
use App\Models\ESocialEvent;
use App\Models\Tenant;
use App\Services\ESocial\ESocialTransmissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ProcessESocialBatchJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_implements_should_queue_contract(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new ProcessESocialBatchJob(eventIds: [1, 2], tenantId: 1)
        );
    }

    public function test_has_expected_retry_policy(): void
    {
        $job = new ProcessESocialBatchJob(eventIds: [1], tenantId: 1);

        $this->assertSame(5, $job->tries, 'Deve ter 5 tries para contingencia eSocial');
        $this->assertSame(3, $job->maxExceptions, 'maxExceptions=3 evita loop infinito');
        $this->assertSame(60, $job->timeout, 'Timeout de 60s por batch');
    }

    public function test_backoff_returns_exponential_sequence(): void
    {
        $job = new ProcessESocialBatchJob(eventIds: [1], tenantId: 1);

        $backoff = $job->backoff();

        $this->assertIsArray($backoff, 'backoff() deve retornar array de delays');
        $this->assertCount(5, $backoff, 'Deve ter um delay por tentativa (tries=5)');
        foreach ($backoff as $delay) {
            $this->assertIsInt($delay);
            $this->assertGreaterThan(0, $delay, 'Todos os delays devem ser positivos');
        }
        // Sequencia exponencial: cada delay >= anterior
        for ($i = 1; $i < count($backoff); $i++) {
            $this->assertGreaterThanOrEqual(
                $backoff[$i - 1],
                $backoff[$i],
                'Sequencia de backoff deve ser nao-decrescente'
            );
        }
    }

    public function test_dispatches_to_queue_with_event_ids_bound(): void
    {
        Queue::fake();

        ProcessESocialBatchJob::dispatch([101, 102, 103], 42);

        Queue::assertPushed(
            ProcessESocialBatchJob::class,
            function (ProcessESocialBatchJob $job) {
                $ref = new \ReflectionObject($job);
                $eventIds = $ref->getProperty('eventIds');
                $eventIds->setAccessible(true);
                $tenantId = $ref->getProperty('tenantId');
                $tenantId->setAccessible(true);

                return $eventIds->getValue($job) === [101, 102, 103]
                    && $tenantId->getValue($job) === 42;
            }
        );
    }

    public function test_handle_calls_service_and_increments_retry_count(): void
    {
        $tenant = Tenant::factory()->create();
        $events = ESocialEvent::factory()
            ->count(3)
            ->pending()
            ->create(['tenant_id' => $tenant->id, 'retry_count' => 0]);

        $eventIds = $events->pluck('id')->toArray();

        /** @var ESocialTransmissionService&MockInterface $serviceMock */
        $serviceMock = Mockery::mock(ESocialTransmissionService::class);
        $serviceMock->shouldReceive('transmitBatch')
            ->once()
            ->with($eventIds)
            ->andReturn([
                'batch_id' => 'BATCH-TEST-001',
                'protocol_number' => 'PROTO-999',
                'events_sent' => 3,
            ]);

        $job = new ProcessESocialBatchJob(eventIds: $eventIds, tenantId: $tenant->id);
        $job->handle($serviceMock);

        // Verifica que retry_count foi incrementado em TODOS os eventos do batch
        foreach ($events as $event) {
            $this->assertSame(
                1,
                $event->fresh()->retry_count,
                "retry_count deve ser incrementado apos transmissao (event {$event->id})"
            );
        }
    }

    public function test_failed_marks_events_as_rejected_with_error_message(): void
    {
        $tenant = Tenant::factory()->create();
        $events = ESocialEvent::factory()
            ->count(2)
            ->pending()
            ->create(['tenant_id' => $tenant->id]);

        $eventIds = $events->pluck('id')->toArray();

        $job = new ProcessESocialBatchJob(eventIds: $eventIds, tenantId: $tenant->id);
        $job->failed(new RuntimeException('Sefaz contingency: timeout permanente'));

        foreach ($events as $event) {
            $refreshed = $event->fresh();
            $this->assertSame(
                'rejected',
                $refreshed->status,
                "Apos failed() evento {$event->id} deve ficar rejected"
            );
            $this->assertStringContainsString(
                'Transmissão falhou após todas as tentativas',
                (string) $refreshed->error_message,
                'error_message deve registrar falha permanente'
            );
            $this->assertStringContainsString(
                'timeout permanente',
                (string) $refreshed->error_message,
                'error_message deve incluir mensagem original da exception'
            );
        }
    }
}
