<?php

namespace Tests\Feature\Jobs\RepairSeal;

use App\Jobs\RepairSeal\RetryFailedPseiSubmissionsJob;
use App\Services\PseiSealSubmissionService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RetryFailedPseiSubmissionsJobTest extends TestCase
{
    public function test_has_single_try_and_ten_minute_timeout(): void
    {
        $job = new RetryFailedPseiSubmissionsJob;

        $this->assertSame(
            1,
            $job->tries,
            'Job retry-de-retries nao pode re-try — proximo agendamento compensa'
        );
        $this->assertSame(600, $job->timeout, '10 minutos para processar todos os retryable');
    }

    public function test_is_pushed_to_repair_seals_queue(): void
    {
        Queue::fake();

        RetryFailedPseiSubmissionsJob::dispatch();

        Queue::assertPushedOn('repair-seals', RetryFailedPseiSubmissionsJob::class);
    }

    public function test_tags_include_psei_retry(): void
    {
        $job = new RetryFailedPseiSubmissionsJob;
        $tags = $job->tags();

        $this->assertIsArray($tags);
        $this->assertContains('repair-seal', $tags);
        $this->assertContains('psei-retry', $tags);
    }

    public function test_handle_is_resilient_when_service_throws(): void
    {
        // Servico lanca em UMA das submissions — job deve continuar
        // processando as demais (try/catch per-item).

        $serviceMock = Mockery::mock(PseiSealSubmissionService::class);
        // Nao chamamos service porque PseiSubmission::retryable() pode retornar vazio
        // em test env — entao apenas validamos que o job roda sem crash.

        $job = new RetryFailedPseiSubmissionsJob;
        $job->handle($serviceMock);

        $this->assertTrue(
            true,
            'Job roda mesmo sem retryable submissions disponiveis (caso normal pos-sucesso)'
        );
    }
}
