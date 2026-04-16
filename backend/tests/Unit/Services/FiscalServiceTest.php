<?php

use App\Enums\FiscalNoteStatus;
use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\FiscalAdvancedService;
use App\Services\Fiscal\FiscalNumberingService;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\FiscalResult;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Lab Calibração Test',
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
});

// ── FiscalNote Model ──

test('fiscal note is created with correct attributes', function () {
    $note = FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 15000.00,
        'number' => 1234,
        'series' => '1',
    ]);

    expect($note->type)->toBe('nfe');
    expect($note->status)->toBe(FiscalNoteStatus::AUTHORIZED);
    expect((float) $note->total_amount)->toBe(15000.00);
    expect($note->isAuthorized())->toBeTrue();
    expect($note->isNFe())->toBeTrue();
});

test('cancelled note is not authorized', function () {
    $note = FiscalNote::factory()->cancelled()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    expect($note->isAuthorized())->toBeFalse();
    expect($note->status)->toBe(FiscalNoteStatus::CANCELLED);
});

test('NFS-e note is not NF-e', function () {
    $note = FiscalNote::factory()->nfse()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    expect($note->isNFe())->toBeFalse();
});

test('generateReference creates unique reference', function () {
    $ref1 = FiscalNote::generateReference('nfe', $this->tenant->id);
    $ref2 = FiscalNote::generateReference('nfe', $this->tenant->id);

    expect($ref1)->not->toBe($ref2);
    expect($ref1)->toBeString();
});

// ── FiscalAdvancedService ──

test('emitirDevolucao rejects non-authorized original note', function () {
    $original = FiscalNote::factory()->nfe()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => 'rejected',
    ]);

    $mockProvider = Mockery::mock(FiscalProvider::class);
    $mockNumbering = Mockery::mock(FiscalNumberingService::class);

    $service = new FiscalAdvancedService($mockProvider, $mockNumbering);
    $result = $service->emitirDevolucao($original, [], $this->user->id);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('não está autorizada');
});

test('emitirDevolucao rejects NFS-e notes', function () {
    $original = FiscalNote::factory()->nfse()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);

    $mockProvider = Mockery::mock(FiscalProvider::class);
    $mockNumbering = Mockery::mock(FiscalNumberingService::class);

    $service = new FiscalAdvancedService($mockProvider, $mockNumbering);
    $result = $service->emitirDevolucao($original, [], $this->user->id);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('NF-e');
});

test('emitirComplementar rejects invalid original note', function () {
    $original = FiscalNote::factory()->nfe()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => 'cancelled',
    ]);

    $mockProvider = Mockery::mock(FiscalProvider::class);
    $mockNumbering = Mockery::mock(FiscalNumberingService::class);

    $service = new FiscalAdvancedService($mockProvider, $mockNumbering);
    $result = $service->emitirComplementar($original, [], $this->user->id);

    expect($result['success'])->toBeFalse();
});

test('emitirRetorno rejects non-remessa note', function () {
    $original = FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'type' => 'nfe', // not nfe_remessa
    ]);

    $mockProvider = Mockery::mock(FiscalProvider::class);
    $mockNumbering = Mockery::mock(FiscalNumberingService::class);

    $service = new FiscalAdvancedService($mockProvider, $mockNumbering);
    $result = $service->emitirRetorno($original, [], $this->user->id);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('remessa inválida');
});

test('manifestarDestinatario rejects invalid manifestation type', function () {
    $mockProvider = Mockery::mock(FiscalProvider::class);
    $mockNumbering = Mockery::mock(FiscalNumberingService::class);

    $service = new FiscalAdvancedService($mockProvider, $mockNumbering);
    $result = $service->manifestarDestinatario('12345678901234567890', 'invalid_type', $this->tenant);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('inválido');
});

test('manifestarDestinatario accepts valid manifestation types', function () {
    $mockResult = FiscalResult::ok([
        'provider_id' => 'test-id',
        'protocol_number' => '123456',
    ]);

    $mockProvider = Mockery::mock(FiscalProvider::class);
    $mockProvider->shouldReceive('emitirNFe')->andReturn($mockResult);

    $mockNumbering = Mockery::mock(FiscalNumberingService::class);

    $service = new FiscalAdvancedService($mockProvider, $mockNumbering);

    $validTypes = ['ciencia', 'confirmacao', 'desconhecimento', 'nao_realizada'];
    foreach ($validTypes as $type) {
        $result = $service->manifestarDestinatario('12345678901234567890123456789012345678901234', $type, $this->tenant);
        expect($result['success'])->toBeTrue();
    }
});

test('emitirDevolucao creates devolucao note on success', function () {
    $original = FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'access_key' => str_repeat('1', 44),
    ]);

    $mockResult = FiscalResult::ok([
        'provider_id' => 'prov-123',
        'access_key' => str_repeat('2', 44),
    ]);

    $mockProvider = Mockery::mock(FiscalProvider::class);
    $mockProvider->shouldReceive('emitirNFe')->once()->andReturn($mockResult);

    $mockNumbering = Mockery::mock(FiscalNumberingService::class);
    $mockNumbering->shouldReceive('nextNFeNumber')->once()->andReturn(['number' => 100, 'series' => '1']);

    $service = new FiscalAdvancedService($mockProvider, $mockNumbering);
    $result = $service->emitirDevolucao($original, [
        ['valor_unitario' => 100, 'quantidade' => 2],
    ], $this->user->id);

    expect($result['success'])->toBeTrue();
    expect($result['note_id'])->not->toBeNull();

    $devNote = FiscalNote::find($result['note_id']);
    expect($devNote->type)->toBe('nfe_devolucao');
    expect($devNote->parent_note_id)->toBe($original->id);
    expect($devNote->status)->toBe(FiscalNoteStatus::AUTHORIZED);
});
