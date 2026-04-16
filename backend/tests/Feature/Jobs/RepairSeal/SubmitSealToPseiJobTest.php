<?php

namespace Tests\Feature\Jobs\RepairSeal;

use App\Jobs\RepairSeal\SubmitSealToPseiJob;
use App\Models\InmetroSeal;
use App\Services\PseiSealSubmissionService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SubmitSealToPseiJobTest extends TestCase
{
    public function test_has_three_tries_with_progressive_backoff(): void
    {
        $job = new SubmitSealToPseiJob(sealId: 1);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 1800], $job->backoff, 'Backoff: 1min, 5min, 30min');
    }

    public function test_is_pushed_to_repair_seals_queue(): void
    {
        Queue::fake();

        SubmitSealToPseiJob::dispatch(42);

        Queue::assertPushedOn('repair-seals', SubmitSealToPseiJob::class);
    }

    public function test_tags_include_seal_id(): void
    {
        $job = new SubmitSealToPseiJob(sealId: 777);
        $tags = $job->tags();

        $this->assertContains('repair-seal', $tags);
        $this->assertContains('psei', $tags);
        $this->assertContains('seal:777', $tags);
    }

    public function test_handle_does_not_submit_when_seal_not_found(): void
    {
        $serviceMock = Mockery::mock(PseiSealSubmissionService::class);
        $serviceMock->shouldNotReceive('submitSeal');

        $job = new SubmitSealToPseiJob(sealId: 99999);
        $job->handle($serviceMock);

        $serviceMock->shouldNotHaveReceived('submitSeal');
    }

    public function test_handle_does_not_submit_when_seal_already_confirmed(): void
    {
        $serviceMock = Mockery::mock(PseiSealSubmissionService::class);
        $serviceMock->shouldNotReceive('submitSeal');

        $seal = InmetroSeal::factory()->registered()->create();

        $job = new SubmitSealToPseiJob(sealId: $seal->id);
        $job->handle($serviceMock);

        $serviceMock->shouldNotHaveReceived('submitSeal');
        $this->assertDatabaseHas('inmetro_seals', [
            'id' => $seal->id,
            'psei_status' => InmetroSeal::PSEI_CONFIRMED,
        ]);
    }
}
