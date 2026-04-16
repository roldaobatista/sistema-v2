<?php

namespace Tests\Feature\Jobs\RepairSeal;

use App\Jobs\RepairSeal\CheckSealDeadlinesJob;
use App\Services\RepairSealDeadlineService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CheckSealDeadlinesJobTest extends TestCase
{
    public function test_has_single_try_and_five_minute_timeout(): void
    {
        $job = new CheckSealDeadlinesJob;

        $this->assertSame(1, $job->tries, 'Job agendado nao deve retry — proximo run cobre');
        $this->assertSame(300, $job->timeout, '5 minutos e suficiente para scan completo de deadlines');
    }

    public function test_is_pushed_to_repair_seals_queue(): void
    {
        Queue::fake();

        CheckSealDeadlinesJob::dispatch();

        Queue::assertPushedOn('repair-seals', CheckSealDeadlinesJob::class);
    }

    public function test_tags_include_repair_seal(): void
    {
        $job = new CheckSealDeadlinesJob;
        $tags = $job->tags();

        $this->assertIsArray($tags);
        $this->assertContains('repair-seal', $tags);
    }

    public function test_handle_delegates_to_deadline_service(): void
    {
        $serviceMock = Mockery::mock(RepairSealDeadlineService::class);
        $serviceMock->shouldReceive('checkAllDeadlines')
            ->once()
            ->andReturn([
                'checked' => 10,
                'alerts_created' => 3,
                'overdue' => 1,
            ]);

        $job = new CheckSealDeadlinesJob;
        $job->handle($serviceMock);

        $this->assertTrue(true, 'Job delegou corretamente ao RepairSealDeadlineService');
    }
}
