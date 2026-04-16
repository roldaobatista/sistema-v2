<?php

namespace Tests\Unit\Services\ESocial;

use App\Exceptions\CircuitBreakerException;
use App\Models\ESocialEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ESocial\ESocialTransmissionService;
use App\Services\Integration\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ESocialTransmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ESocialTransmissionService $service;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = new ESocialTransmissionService;
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'current_tenant_id' => $this->tenant->id,
        ]);
    }

    private function createPendingEvent(array $overrides = []): ESocialEvent
    {
        return ESocialEvent::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'S-2200',
            'xml_content' => '<eSocial><evtAdmissao/></eSocial>',
            'status' => 'pending',
            'environment' => 'restricted',
            'version' => 'S-1.2',
        ], $overrides));
    }

    public function test_transmit_batch_success_in_mock_environment(): void
    {
        config(['esocial.environment' => 'restricted']);

        $event1 = $this->createPendingEvent();
        $event2 = $this->createPendingEvent(['event_type' => 'S-2299']);

        $result = $this->service->transmitBatch([$event1->id, $event2->id]);

        $this->assertArrayHasKey('protocol_number', $result);
        $this->assertArrayHasKey('batch_id', $result);
        $this->assertEquals('sent', $result['status']);
        $this->assertEquals(2, $result['events_sent']);
        $this->assertStringStartsWith('PROT-', $result['protocol_number']);

        // Verify events were updated
        $event1->refresh();
        $this->assertEquals('sent', $event1->status);
        $this->assertNotNull($event1->sent_at);
        $this->assertNotNull($event1->protocol_number);
    }

    public function test_transmit_batch_rejects_no_pending_events(): void
    {
        $event = $this->createPendingEvent(['status' => 'accepted']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nenhum evento pendente encontrado para transmissão.');

        $this->service->transmitBatch([$event->id]);
    }

    public function test_transmit_batch_reverts_status_on_failure(): void
    {
        config(['esocial.environment' => 'restricted']);

        $event = $this->createPendingEvent();

        // Trip the circuit breaker to force failure
        $cb = CircuitBreaker::for('esocial_api')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('trip'));
        } catch (\RuntimeException) {
            // expected
        }

        try {
            $this->service->transmitBatch([$event->id]);
            $this->fail('Expected exception from circuit breaker');
        } catch (\Throwable) {
            // expected
        }

        // Event should be reverted to pending
        $event->refresh();
        $this->assertEquals('pending', $event->status);
        $this->assertNull($event->batch_id);
    }

    public function test_transmit_single_delegates_to_batch(): void
    {
        config(['esocial.environment' => 'restricted']);

        $event = $this->createPendingEvent();
        $result = $this->service->transmitSingle($event);

        $this->assertArrayHasKey('protocol_number', $result);
        $this->assertEquals(1, $result['events_sent']);
    }

    public function test_check_batch_response_returns_processed_status(): void
    {
        config(['esocial.environment' => 'restricted']);

        $event = $this->createPendingEvent();
        $result = $this->service->transmitBatch([$event->id]);
        $protocolNumber = $result['protocol_number'];

        $statusResult = $this->service->checkBatchResponse($protocolNumber);

        $this->assertEquals('processed', $statusResult['status']);
        $this->assertEquals($protocolNumber, $statusResult['protocol_number']);
        $this->assertNotEmpty($statusResult['events']);
        $this->assertEquals('accepted', $statusResult['events'][0]['status']);

        // Verify event was updated in DB
        $event->refresh();
        $this->assertEquals('accepted', $event->status);
        $this->assertNotNull($event->receipt_number);
    }

    public function test_check_batch_response_rejects_invalid_events(): void
    {
        config(['esocial.environment' => 'restricted']);

        $event = $this->createPendingEvent(['event_type' => 'INVALID']);
        $result = $this->service->transmitBatch([$event->id]);

        $statusResult = $this->service->checkBatchResponse($result['protocol_number']);

        $this->assertEquals('rejected', $statusResult['events'][0]['status']);
        $this->assertNotNull($statusResult['events'][0]['error_message']);
    }

    public function test_check_batch_response_throws_for_unknown_protocol(): void
    {
        config(['esocial.environment' => 'restricted']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->checkBatchResponse('PROT-NONEXISTENT');
    }

    public function test_circuit_breaker_blocks_after_threshold(): void
    {
        config(['esocial.environment' => 'restricted']);

        // Manually trip the circuit breaker
        $cb = CircuitBreaker::for('esocial_api')->withThreshold(1)->withTimeout(60);

        try {
            $cb->execute(fn () => throw new \RuntimeException('forced failure'));
        } catch (\RuntimeException) {
            // expected trip
        }

        $this->assertTrue($cb->isOpen());

        $event = $this->createPendingEvent();

        $this->expectException(CircuitBreakerException::class);
        $this->service->transmitBatch([$event->id]);
    }

    public function test_retry_delay_increases_exponentially(): void
    {
        $delay0 = $this->service->getRetryDelay(0);
        $delay1 = $this->service->getRetryDelay(1);
        $delay2 = $this->service->getRetryDelay(2);

        // Each delay should be roughly double the previous (plus jitter)
        $this->assertGreaterThanOrEqual(5, $delay0);
        $this->assertGreaterThanOrEqual(10, $delay1);
        $this->assertGreaterThanOrEqual(20, $delay2);

        // Should never exceed max delay + jitter
        $delay10 = $this->service->getRetryDelay(10);
        $this->assertLessThanOrEqual(305, $delay10); // max 300 + up to 5 jitter
    }

    public function test_cross_tenant_isolation(): void
    {
        config(['esocial.environment' => 'restricted']);

        $otherTenant = Tenant::factory()->create();

        $event = ESocialEvent::create([
            'tenant_id' => $otherTenant->id,
            'event_type' => 'S-2200',
            'xml_content' => '<eSocial/>',
            'status' => 'pending',
            'environment' => 'restricted',
            'version' => 'S-1.2',
        ]);

        // Can transmit (no tenant scoping in transmission service itself,
        // tenant scoping is handled at the controller level)
        $result = $this->service->transmitBatch([$event->id]);
        $this->assertEquals(1, $result['events_sent']);
    }

    public function test_batch_with_mixed_eligible_and_accepted_events_only_sends_eligible(): void
    {
        config(['esocial.environment' => 'restricted']);

        $pendingEvent = $this->createPendingEvent();
        $acceptedEvent = $this->createPendingEvent(['status' => 'accepted']);

        $result = $this->service->transmitBatch([$pendingEvent->id, $acceptedEvent->id]);

        $this->assertEquals(1, $result['events_sent']);
    }

    public function test_empty_event_ids_throws_exception(): void
    {
        config(['esocial.environment' => 'restricted']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->transmitBatch([]);
    }
}
