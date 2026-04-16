<?php

use App\Models\Customer;
use App\Models\Import;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Model::unguard();
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->service = app(ImportService::class);
});

test('getFields returns fields for customers entity', function () {
    $fields = $this->service->getFields(Import::ENTITY_CUSTOMERS);

    expect($fields)->toBeArray();
    expect($fields)->not->toBeEmpty();

    $keys = collect($fields)->pluck('key')->toArray();
    expect($keys)->toContain('name');
    expect($keys)->toContain('document');
});

test('getFields returns fields for products entity', function () {
    $fields = $this->service->getFields(Import::ENTITY_PRODUCTS);

    expect($fields)->not->toBeEmpty();
    $keys = collect($fields)->pluck('key')->toArray();
    expect($keys)->toContain('name');
    expect($keys)->toContain('code');
});

test('getFields returns empty array for unknown entity', function () {
    $fields = $this->service->getFields('unknown_entity');

    expect($fields)->toBeEmpty();
});

test('countCsvRows counts data rows excluding header', function () {
    $csvContent = "Nome;Documento;Email\n";
    $csvContent .= "Empresa A;12345678000190;a@test.com\n";
    $csvContent .= "Empresa B;98765432000110;b@test.com\n";
    $csvContent .= "Empresa C;11111111000111;c@test.com\n";

    $tmpFile = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tmpFile, $csvContent);

    $count = $this->service->countCsvRows($tmpFile);

    expect($count)->toBe(3);

    @unlink($tmpFile);
});

test('countCsvRows returns zero for empty file', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tmpFile, '');

    $count = $this->service->countCsvRows($tmpFile);

    expect($count)->toBe(0);

    @unlink($tmpFile);
});

test('exportErrorCsv returns empty string when no errors', function () {
    $import = new Import(['error_log' => []]);

    $csv = $this->service->exportErrorCsv($import);

    expect($csv)->toBe('');
});

test('exportErrorCsv generates CSV with error data', function () {
    $import = new Import([
        'error_log' => [
            ['line' => 2, 'message' => 'Campo obrigatório', 'data' => ['name' => 'Test']],
            ['line' => 5, 'message' => 'Email inválido', 'data' => ['email' => 'invalid']],
        ],
    ]);

    $csv = $this->service->exportErrorCsv($import);

    expect($csv)->toContain('Linha');
    expect($csv)->toContain('Campo obrigatório');
    expect($csv)->toContain('Email inválido');
});

test('rollbackImport fails when no imported IDs exist', function () {
    $import = Import::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Import::ENTITY_CUSTOMERS,
        'status' => Import::STATUS_DONE,
        'file_name' => 'test.csv',
        'imported_ids' => [],
        'user_id' => $this->user->id,
    ]);

    expect(fn () => $this->service->rollbackImport($import))
        ->toThrow(HttpException::class);
});

test('rollbackImport deletes imported records', function () {
    $customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $import = Import::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Import::ENTITY_CUSTOMERS,
        'status' => Import::STATUS_DONE,
        'file_name' => 'test.csv',
        'imported_ids' => [$customer1->id, $customer2->id],
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->rollbackImport($import);

    expect($result['deleted'])->toBe(2);
    expect($result['total'])->toBe(2);
    expect($result['failed'])->toBe(0);
});

test('rollbackImport updates import status', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $import = Import::create([
        'tenant_id' => $this->tenant->id,
        'entity_type' => Import::ENTITY_CUSTOMERS,
        'status' => Import::STATUS_DONE,
        'file_name' => 'test.csv',
        'imported_ids' => [$customer->id],
        'user_id' => $this->user->id,
    ]);

    $this->service->rollbackImport($import);

    expect($import->fresh()->status)->toBe(Import::STATUS_ROLLED_BACK);
});

test('generateSampleExcel returns non-empty content for valid entity', function () {
    $content = $this->service->generateSampleExcel(Import::ENTITY_CUSTOMERS);

    expect($content)->not->toBeEmpty();
    // XLSX files start with PK (zip format)
    expect(substr($content, 0, 2))->toBe('PK');
});

test('generateSampleExcel returns empty string for unknown entity', function () {
    $content = $this->service->generateSampleExcel('unknown');

    expect($content)->toBe('');
});
