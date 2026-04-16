<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EmitFiscalNoteJob;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Fiscal\FiscalProvider;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class EmitFiscalNoteJobTest extends TestCase
{
    public function test_has_unique_job_contract(): void
    {
        // ShouldBeUnique evita dispatch duplicado por tenant+invoice+note_type
        $this->assertInstanceOf(
            ShouldBeUnique::class,
            new EmitFiscalNoteJob(tenantId: 1, invoiceId: 1, noteType: 'nfe')
        );
    }

    public function test_unique_id_includes_tenant_invoice_and_type(): void
    {
        $job = new EmitFiscalNoteJob(tenantId: 42, invoiceId: 777, noteType: 'nfse');

        $this->assertSame(
            '42:777:nfse',
            $job->uniqueId(),
            'uniqueId evita dispatch concorrente do mesmo tenant+invoice+type'
        );
    }

    public function test_has_expected_retry_policy(): void
    {
        $job = new EmitFiscalNoteJob(tenantId: 1, invoiceId: 1, noteType: 'nfe');

        $this->assertSame(3, $job->tries);
        $this->assertSame(120, $job->timeout);
        $this->assertSame(60, $job->backoff);
        $this->assertSame(900, $job->uniqueFor, 'Unique lock por 15 minutos evita duplicacao em burst');
    }

    public function test_dispatches_to_fiscal_queue(): void
    {
        Queue::fake();

        EmitFiscalNoteJob::dispatch(tenantId: 1, invoiceId: 99, noteType: 'nfe');

        Queue::assertPushedOn('fiscal', EmitFiscalNoteJob::class);
    }

    public function test_handle_skips_when_invoice_not_found(): void
    {
        $fiscalProviderMock = Mockery::mock(FiscalProvider::class);
        $fiscalProviderMock->shouldNotReceive('emit'); // nao pode chamar provider se invoice nao existe

        $job = new EmitFiscalNoteJob(tenantId: 999, invoiceId: 99999, noteType: 'nfe');
        $job->handle($fiscalProviderMock);

        $this->assertTrue(true, 'handle() retorna sem chamar FiscalProvider quando invoice nao existe');
    }

    public function test_handle_skips_when_invoice_is_cancelled(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Invoice::STATUS_CANCELLED,
        ]);

        $fiscalProviderMock = Mockery::mock(FiscalProvider::class);
        $fiscalProviderMock->shouldNotReceive('emit');

        $job = new EmitFiscalNoteJob(
            tenantId: $tenant->id,
            invoiceId: $invoice->id,
            noteType: 'nfe'
        );
        $job->handle($fiscalProviderMock);

        // Verificacao: fiscal_status NAO foi atualizado (pois invoice estava cancelled)
        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id,
            'fiscal_status' => Invoice::FISCAL_STATUS_EMITTING,
        ]);
    }
}
