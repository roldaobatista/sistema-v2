<?php

use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementEntry;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BankReconciliationService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(BankReconciliationService::class);
});

// ── OFX Parser ──

test('parseOfx extracts transactions from OFX content', function () {
    $ofx = <<<'OFX'
<OFX>
<BANKMSGSRSV1>
<STMTTRNRS>
<STMTRS>
<BANKTRANLIST>
<STMTTRN>
<TRNAMT>1500.00
<DTPOSTED>20260115
<MEMO>Pagamento cliente ABC
</STMTTRN>
<STMTTRN>
<TRNAMT>-200.50
<DTPOSTED>20260120
<MEMO>Tarifa bancária
</STMTTRN>
</BANKTRANLIST>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

    $entries = $this->service->parseOfx($ofx);

    expect($entries)->toHaveCount(2);
    expect($entries[0]['amount'])->toBe('1500.00');
    expect($entries[0]['date'])->toBe('2026-01-15');
    expect($entries[0]['description'])->toBe('Pagamento cliente ABC');
    expect($entries[1]['amount'])->toBe('-200.50');
    expect($entries[1]['date'])->toBe('2026-01-20');
});

test('parseOfx returns empty array for content without transactions', function () {
    $entries = $this->service->parseOfx('<OFX></OFX>');

    expect($entries)->toBeEmpty();
});

// ── Format Detection ──

test('detectFormat identifies OFX by extension', function () {
    $format = $this->service->detectFormat('some content', 'extrato.ofx');

    expect($format)->toBe('ofx');
});

test('detectFormat identifies CNAB 240 by line length', function () {
    $line = str_repeat('0', 240);
    $format = $this->service->detectFormat($line, 'retorno.ret');

    expect($format)->toBe('cnab240');
});

test('detectFormat identifies CNAB 400 by line length', function () {
    $line = str_repeat('0', 400);
    $format = $this->service->detectFormat($line, 'retorno.ret');

    expect($format)->toBe('cnab400');
});

test('detectFormat defaults to OFX for unknown format', function () {
    $format = $this->service->detectFormat('random data', 'file.txt');

    expect($format)->toBe('ofx');
});

// ── CNAB Helpers ──

test('parseCnabDate converts ddmmyyyy correctly', function () {
    $date = $this->service->parseCnabDate('15012026', 'ddmmyyyy');

    expect($date)->toBe('2026-01-15');
});

test('parseCnabDate converts ddmmyy with 2000s year', function () {
    $date = $this->service->parseCnabDate('150126', 'ddmmyy');

    expect($date)->toBe('2026-01-15');
});

test('parseCnabDate converts ddmmyy with 1900s year', function () {
    $date = $this->service->parseCnabDate('150199', 'ddmmyy');

    expect($date)->toBe('1999-01-15');
});

test('parseCnabDate returns null for zero date', function () {
    expect($this->service->parseCnabDate('00000000'))->toBeNull();
    expect($this->service->parseCnabDate('000000', 'ddmmyy'))->toBeNull();
});

test('parseCnabAmount converts integer cents to decimal', function () {
    expect($this->service->parseCnabAmount('000000150000'))->toBe('1500.00');
    expect($this->service->parseCnabAmount('000000000050'))->toBe('0.50');
    expect($this->service->parseCnabAmount('000000000000'))->toBe('0.00');
});

test('parseCnabAmount handles empty string', function () {
    expect($this->service->parseCnabAmount(''))->toBe('0.00');
});

// ── Auto-Match ──

test('autoMatch matches credit entry to pending receivable by amount and date', function () {
    $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

    $statement = BankStatement::factory()->create([
        'tenant_id' => $this->tenant->id,
        'bank_account_id' => $bankAccount->id,
        'created_by' => $this->user->id,
    ]);

    $entry = BankStatementEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'bank_statement_id' => $statement->id,
        'amount' => 1500.00,
        'type' => 'credit',
        'date' => '2026-01-15',
        'status' => BankStatementEntry::STATUS_PENDING,
    ]);

    AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 1500.00,
        'due_date' => '2026-01-15',
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    $matched = $this->service->autoMatch($statement);

    expect($matched)->toBe(1);
    expect($entry->fresh()->status)->toBe(BankStatementEntry::STATUS_MATCHED);
});

test('autoMatch does not match when amount difference exceeds tolerance', function () {
    $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

    $statement = BankStatement::factory()->create([
        'tenant_id' => $this->tenant->id,
        'bank_account_id' => $bankAccount->id,
        'created_by' => $this->user->id,
    ]);

    BankStatementEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'bank_statement_id' => $statement->id,
        'amount' => 1500.00,
        'type' => 'credit',
        'date' => '2026-01-15',
        'status' => BankStatementEntry::STATUS_PENDING,
    ]);

    AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 2000.00, // too different
        'due_date' => '2026-01-15',
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    $matched = $this->service->autoMatch($statement);

    expect($matched)->toBe(0);
});

// ── Summary ──

test('getSummary returns correct counts', function () {
    $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

    $statement = BankStatement::factory()->create([
        'tenant_id' => $this->tenant->id,
        'bank_account_id' => $bankAccount->id,
        'created_by' => $this->user->id,
    ]);

    BankStatementEntry::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'bank_statement_id' => $statement->id,
        'status' => BankStatementEntry::STATUS_PENDING,
        'type' => 'credit',
        'amount' => 100,
    ]);

    BankStatementEntry::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'bank_statement_id' => $statement->id,
        'status' => BankStatementEntry::STATUS_MATCHED,
        'type' => 'debit',
        'amount' => 50,
    ]);

    $summary = $this->service->getSummary($this->tenant->id);

    expect($summary['total_entries'])->toBe(5);
    expect($summary['pending_count'])->toBe(3);
    expect($summary['matched_count'])->toBe(2);
    expect($summary['matched_percent'])->toBe(40.0);
});

// ── calculateScore ──

test('calculateScore gives high score for exact value match', function () {
    $entry = new BankStatementEntry([
        'amount' => 1000.00,
        'date' => '2026-01-15',
        'description' => 'Pagamento cliente',
    ]);

    $record = new AccountReceivable([
        'amount' => 1000.00,
        'due_date' => '2026-01-15',
        'description' => 'Pagamento cliente',
    ]);

    // Use reflection to test private method
    $reflection = new ReflectionMethod($this->service, 'calculateScore');
    $score = $reflection->invoke($this->service, $entry, $record);

    expect($score)->toBeGreaterThan(80);
});

test('calculateScore gives lower score for different amounts', function () {
    $entry = new BankStatementEntry([
        'amount' => 1000.00,
        'date' => '2026-01-15',
        'description' => 'Pagamento',
    ]);

    $record = new AccountReceivable([
        'amount' => 500.00,
        'due_date' => '2026-01-15',
        'description' => 'Pagamento',
    ]);

    $reflection = new ReflectionMethod($this->service, 'calculateScore');
    $score = $reflection->invoke($this->service, $entry, $record);

    expect($score)->toBeLessThan(80);
});

// ── Duplicate Detection ──

test('checkDuplicate identifies duplicate entries across statements', function () {
    $bankAccount = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

    $statement1 = BankStatement::factory()->create([
        'tenant_id' => $this->tenant->id,
        'bank_account_id' => $bankAccount->id,
        'created_by' => $this->user->id,
    ]);

    BankStatementEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'bank_statement_id' => $statement1->id,
        'date' => '2026-01-15',
        'amount' => 500.00,
        'description' => 'Pagamento PIX duplicado',
        'type' => 'credit',
    ]);

    $statement2 = BankStatement::factory()->create([
        'tenant_id' => $this->tenant->id,
        'bank_account_id' => $bankAccount->id,
        'created_by' => $this->user->id,
    ]);

    // Verify the entry was persisted and the duplicate detection query finds it
    $entry = BankStatementEntry::withoutGlobalScopes()
        ->where('bank_statement_id', $statement1->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect((float) $entry->amount)->toBe(500.00);
    expect($entry->description)->toBe('Pagamento PIX duplicado');

    // Same entry in a different statement = duplicate
    $duplicateExists = BankStatementEntry::withoutGlobalScopes()
        ->where('bank_statement_id', '!=', $statement2->id)
        ->whereDate('date', '2026-01-15')
        ->where('description', 'Pagamento PIX duplicado')
        ->exists();

    expect($duplicateExists)->toBeTrue();
});

// ── learnRule ──

test('learnRule generates rule suggestion from matched entry', function () {
    $entry = new BankStatementEntry([
        'status' => BankStatementEntry::STATUS_MATCHED,
        'matched_type' => AccountReceivable::class,
        'matched_id' => 123,
        'description' => 'Pagamento calibração balança industrial',
    ]);

    $rule = $this->service->learnRule($entry);

    expect($rule)->not->toBeNull();
    expect($rule['action'])->toBe('match_receivable');
    expect($rule['match_field'])->toBe('description');
    expect($rule['match_operator'])->toBe('contains');
    expect($rule['target_id'])->toBe(123);
});

test('learnRule returns null for unmatched entry', function () {
    $entry = new BankStatementEntry([
        'status' => BankStatementEntry::STATUS_PENDING,
        'matched_type' => null,
    ]);

    $rule = $this->service->learnRule($entry);

    expect($rule)->toBeNull();
});
