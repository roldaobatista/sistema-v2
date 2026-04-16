<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Services\DocumentSigningService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DocumentSigningServiceTest extends TestCase
{
    private Tenant $tenant;

    private DocumentSigningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create(['signing_key' => null]);
        $this->service = new DocumentSigningService;
    }

    public function test_sign_returns_64_char_hex_string(): void
    {
        $signature = $this->service->sign('test content', $this->tenant->id);

        $this->assertEquals(64, strlen($signature));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature);
    }

    public function test_sign_auto_generates_key_on_first_use(): void
    {
        $this->assertNull($this->tenant->signing_key);

        $this->service->sign('test', $this->tenant->id);

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->signing_key);
        $this->assertEquals(64, strlen($this->tenant->signing_key));
    }

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $content = 'AFD export data here';
        $signature = $this->service->sign($content, $this->tenant->id);

        $this->assertTrue($this->service->verify($content, $signature, $this->tenant->id));
    }

    public function test_verify_returns_false_for_tampered_content(): void
    {
        $signature = $this->service->sign('original content', $this->tenant->id);

        $this->assertFalse($this->service->verify('tampered content', $signature, $this->tenant->id));
    }

    public function test_verify_returns_false_for_wrong_signature(): void
    {
        $content = 'some data';
        $this->service->sign($content, $this->tenant->id);

        $this->assertFalse($this->service->verify($content, 'wrong_signature', $this->tenant->id));
    }

    public function test_same_content_produces_consistent_signature(): void
    {
        $content = 'consistent test';
        $sig1 = $this->service->sign($content, $this->tenant->id);
        $sig2 = $this->service->sign($content, $this->tenant->id);

        $this->assertEquals($sig1, $sig2);
    }

    public function test_different_tenants_produce_different_signatures(): void
    {
        $tenant2 = Tenant::factory()->create(['signing_key' => null]);
        $content = 'same content';

        $sig1 = $this->service->sign($content, $this->tenant->id);
        $sig2 = $this->service->sign($content, $tenant2->id);

        $this->assertNotEquals($sig1, $sig2);
    }
}
