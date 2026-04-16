<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EmitNFeJob;
use App\Models\FiscalNote;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmitNFeJobTest extends TestCase
{
    public function test_has_expected_retry_policy(): void
    {
        $job = new EmitNFeJob(invoiceId: 1);

        $this->assertSame(3, $job->tries, 'Deve ter 3 tries para garantir retry em falha transiente');
        $this->assertSame([60, 300, 900], $job->backoff, 'Backoff progressivo: 1min, 5min, 15min');
    }

    public function test_dispatches_to_queue(): void
    {
        Queue::fake();

        EmitNFeJob::dispatch(42);

        Queue::assertPushed(EmitNFeJob::class, fn (EmitNFeJob $job) => $job->invoiceId === 42);
    }

    public function test_handle_returns_gracefully_when_invoice_missing(): void
    {
        // Sem invoice no DB — job nao pode lancar exception
        $job = new EmitNFeJob(invoiceId: 99999);

        // handle() deve retornar sem erro (graceful degradation)
        $job->handle();

        // Sem assertion de side effect — apenas garantir que nao crasha
        $this->assertTrue(true, 'handle() retorna silenciosamente quando invoice nao existe');
    }

    public function test_handle_returns_without_creating_note_when_work_order_missing(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'work_order_id' => null, // sem WO vinculada
        ]);

        $job = new EmitNFeJob(invoiceId: $invoice->id);

        // Antes do handle: zero FiscalNotes
        $this->assertSame(0, FiscalNote::count());

        $job->handle();

        // Depois do handle: ainda zero FiscalNotes (WO era null)
        $this->assertSame(
            0,
            FiscalNote::count(),
            'Job nao pode criar FiscalNote quando Invoice nao tem WorkOrder'
        );
    }
}
