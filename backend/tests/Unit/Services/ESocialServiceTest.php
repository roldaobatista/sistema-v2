<?php

namespace Tests\Unit\Services;

use App\Jobs\ProcessESocialBatchJob;
use App\Models\ESocialEvent;
use App\Models\InssBracket;
use App\Models\IrrfBracket;
use App\Models\Payroll;
use App\Models\Rescission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ESocialService;
use App\Services\RescissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ESocialServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private ESocialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create([
            'document' => '12345678000190',
        ]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'cpf' => '12345678900',
            'pis_number' => '12345678901',
            'admission_date' => '2025-01-02',
            'salary' => 3000.00,
            'cbo_code' => '123456',
            'birth_date' => '1990-05-15',
            'gender' => 'M',
            'marital_status' => 'single',
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->service = app(ESocialService::class);
    }

    // ═══════════════════════════════════════════════════════════════
    // S-2200 — Admissao
    // ═══════════════════════════════════════════════════════════════

    public function test_generate_s2200_creates_event_with_correct_type(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertInstanceOf(ESocialEvent::class, $event);
        $this->assertTrue($event->exists);
        $this->assertEquals('S-2200', $event->event_type);
        $this->assertEquals($this->tenant->id, $event->tenant_id);
    }

    public function test_generate_s2200_xml_starts_with_xml_declaration(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertStringStartsWith('<?xml', $event->xml_content);
    }

    public function test_generate_s2200_xml_contains_employee_cpf(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertStringContainsString('12345678900', $event->xml_content);
    }

    public function test_generate_s2200_xml_contains_employee_name(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertStringContainsString($this->user->name, $event->xml_content);
    }

    public function test_generate_s2200_xml_contains_employer_cnpj(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertStringContainsString('12345678000190', $event->xml_content);
    }

    public function test_generate_s2200_xml_contains_salary(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertStringContainsString('3000.00', $event->xml_content);
    }

    public function test_generate_s2200_xml_contains_cbo_code(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertStringContainsString('123456', $event->xml_content);
    }

    public function test_generate_s2200_xml_contains_admission_date(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertStringContainsString('2025-01-02', $event->xml_content);
    }

    public function test_generate_s2200_xml_is_valid_xml(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $doc = new \DOMDocument;
        $result = $doc->loadXML($event->xml_content);
        $this->assertTrue($result, 'S-2200 XML should be valid');
    }

    public function test_generate_s2200_xml_contains_esocial_namespace(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertStringContainsString('esocial.gov.br', $event->xml_content);
    }

    // ═══════════════════════════════════════════════════════════════
    // S-1200 — Remuneracao
    // ═══════════════════════════════════════════════════════════════

    public function test_generate_s1200_creates_event_with_correct_type(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03',
            'status' => 'calculated',
        ]);

        $event = $this->service->generateEvent('S-1200', $payroll, $this->tenant->id);

        $this->assertEquals('S-1200', $event->event_type);
        $this->assertTrue($event->exists);
    }

    public function test_generate_s1200_xml_starts_with_xml_declaration(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03',
            'status' => 'calculated',
        ]);

        $event = $this->service->generateEvent('S-1200', $payroll, $this->tenant->id);

        $this->assertStringStartsWith('<?xml', $event->xml_content);
    }

    public function test_generate_s1200_xml_contains_reference_month(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03',
            'status' => 'calculated',
        ]);

        $event = $this->service->generateEvent('S-1200', $payroll, $this->tenant->id);

        $this->assertStringContainsString('2026-03', $event->xml_content);
    }

    public function test_generate_s1200_xml_contains_employer_cnpj(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03',
            'status' => 'calculated',
        ]);

        $event = $this->service->generateEvent('S-1200', $payroll, $this->tenant->id);

        $this->assertStringContainsString('12345678000190', $event->xml_content);
    }

    public function test_generate_s1200_xml_is_valid_xml(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03',
            'status' => 'calculated',
        ]);

        $event = $this->service->generateEvent('S-1200', $payroll, $this->tenant->id);

        $doc = new \DOMDocument;
        $result = $doc->loadXML($event->xml_content);
        $this->assertTrue($result, 'S-1200 XML should be valid');
    }

    // ═══════════════════════════════════════════════════════════════
    // S-2299 — Desligamento
    // ═══════════════════════════════════════════════════════════════

    public function test_generate_s2299_creates_event_with_correct_type(): void
    {
        $rescission = $this->createRescission();

        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $this->assertEquals('S-2299', $event->event_type);
        $this->assertTrue($event->exists);
    }

    public function test_generate_s2299_xml_starts_with_xml_declaration(): void
    {
        $rescission = $this->createRescission();

        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $this->assertStringStartsWith('<?xml', $event->xml_content);
    }

    public function test_generate_s2299_xml_contains_termination_date(): void
    {
        $rescission = $this->createRescission();

        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $this->assertStringContainsString('2026-03-18', $event->xml_content);
    }

    public function test_generate_s2299_xml_contains_employee_cpf(): void
    {
        $rescission = $this->createRescission();

        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $this->assertStringContainsString('12345678900', $event->xml_content);
    }

    public function test_generate_s2299_xml_contains_employer_cnpj(): void
    {
        $rescission = $this->createRescission();

        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $this->assertStringContainsString('12345678000190', $event->xml_content);
    }

    public function test_generate_s2299_xml_is_valid_xml(): void
    {
        $rescission = $this->createRescission();

        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $doc = new \DOMDocument;
        $result = $doc->loadXML($event->xml_content);
        $this->assertTrue($result, 'S-2299 XML should be valid');
    }

    // ═══════════════════════════════════════════════════════════════
    // Event persistence and metadata
    // ═══════════════════════════════════════════════════════════════

    public function test_event_is_persisted_in_database(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertDatabaseHas('esocial_events', [
            'id' => $event->id,
            'event_type' => 'S-2200',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_event_status_is_generating(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertEquals('generating', $event->status);
    }

    public function test_event_stores_related_type_and_id(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertEquals(User::class, $event->related_type);
        $this->assertEquals($this->user->id, $event->related_id);
    }

    public function test_event_stores_environment_and_version(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->assertNotNull($event->environment);
        $this->assertNotNull($event->version);
    }

    // ═══════════════════════════════════════════════════════════════
    // Unsupported event type
    // ═══════════════════════════════════════════════════════════════

    public function test_unsupported_event_type_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event type S-9999 not supported');

        $this->service->generateEvent('S-9999', $this->user, $this->tenant->id);
    }

    // ═══════════════════════════════════════════════════════════════
    // Batch operations
    // ═══════════════════════════════════════════════════════════════

    public function test_send_batch_marks_events_as_sent(): void
    {
        Bus::fake();

        $event1 = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);
        $event2 = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $batchId = $this->service->sendBatch([$event1->id, $event2->id]);

        $this->assertNotEmpty($batchId);
        $this->assertStringStartsWith('BATCH-', $batchId);

        $event1->refresh();
        $event2->refresh();
        $this->assertEquals('sent', $event1->status);
        $this->assertEquals('sent', $event2->status);
        $this->assertEquals($batchId, $event1->batch_id);
        $this->assertNotNull($event1->sent_at);

        Bus::assertDispatched(ProcessESocialBatchJob::class);
    }

    public function test_check_batch_status_returns_correct_counts(): void
    {
        Bus::fake();

        $event1 = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);
        $event2 = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $batchId = $this->service->sendBatch([$event1->id, $event2->id]);
        $status = $this->service->checkBatchStatus($batchId);

        $this->assertEquals($batchId, $status['batch_id']);
        $this->assertEquals(2, $status['total']);
        $this->assertEquals(2, $status['sent']);
    }

    public function test_process_response_updates_event(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->service->processResponse($event, '<response/>', 'accepted', 'REC-123');

        $event->refresh();
        $this->assertEquals('accepted', $event->status);
        $this->assertEquals('REC-123', $event->receipt_number);
        $this->assertEquals('<response/>', $event->response_xml);
        $this->assertNotNull($event->response_at);
    }

    public function test_process_response_with_error(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $this->service->processResponse($event, '<error/>', 'rejected', null, 'Schema validation failed');

        $event->refresh();
        $this->assertEquals('rejected', $event->status);
        $this->assertEquals('Schema validation failed', $event->error_message);
    }

    // ═══════════════════════════════════════════════════════════════
    // XML Structure Validation (deep assertions)
    // ═══════════════════════════════════════════════════════════════

    public function test_s2200_xml_has_correct_node_hierarchy(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        // Root must be <eSocial>
        $root = $doc->documentElement;
        $this->assertEquals('eSocial', $root->localName);

        // Must contain evtAdmissao > ideEvento, ideEmpregador, trabalhador, vinculo
        $evtAdmissao = $root->getElementsByTagName('evtAdmissao')->item(0);
        $this->assertNotNull($evtAdmissao, 'evtAdmissao node must exist');
        $this->assertNotNull($evtAdmissao->getAttribute('Id'), 'evtAdmissao must have Id attribute');

        $this->assertNotNull($evtAdmissao->getElementsByTagName('ideEvento')->item(0));
        $this->assertNotNull($evtAdmissao->getElementsByTagName('ideEmpregador')->item(0));
        $this->assertNotNull($evtAdmissao->getElementsByTagName('trabalhador')->item(0));
        $this->assertNotNull($evtAdmissao->getElementsByTagName('vinculo')->item(0));
    }

    public function test_s2200_vinculo_contains_info_contrato(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $vinculo = $doc->getElementsByTagName('vinculo')->item(0);
        $this->assertNotNull($vinculo);

        $infoContrato = $vinculo->getElementsByTagName('infoContrato')->item(0);
        $this->assertNotNull($infoContrato, 'vinculo must contain infoContrato');

        // codCBO, vrSalFx, undSalFixo
        $codCBO = $infoContrato->getElementsByTagName('codCBO')->item(0);
        $this->assertNotNull($codCBO);
        $this->assertEquals('123456', $codCBO->textContent);

        $vrSalFx = $infoContrato->getElementsByTagName('vrSalFx')->item(0);
        $this->assertNotNull($vrSalFx);
        $this->assertEquals('3000.00', $vrSalFx->textContent);

        $undSalFixo = $infoContrato->getElementsByTagName('undSalFixo')->item(0);
        $this->assertNotNull($undSalFixo);
        $this->assertEquals('5', $undSalFixo->textContent); // Mensal
    }

    public function test_s2200_cpf_has_11_digits(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $cpfNode = $doc->getElementsByTagName('cpfTrab')->item(0);
        $this->assertNotNull($cpfNode);
        $this->assertMatchesRegularExpression('/^\d{11}$/', $cpfNode->textContent);
    }

    public function test_s2200_cnpj_has_14_digits(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $nrInsc = $doc->getElementsByTagName('nrInsc')->item(0);
        $this->assertNotNull($nrInsc);
        $this->assertMatchesRegularExpression('/^\d{14}$/', $nrInsc->textContent);
    }

    public function test_s2200_tpamb_is_2_in_non_production(): void
    {
        config(['esocial.environment' => 'restricted']);

        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $tpAmb = $doc->getElementsByTagName('tpAmb')->item(0);
        $this->assertNotNull($tpAmb);
        $this->assertEquals('2', $tpAmb->textContent);
    }

    public function test_s2200_tpamb_is_1_in_production(): void
    {
        config(['esocial.environment' => 'production']);

        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $tpAmb = $doc->getElementsByTagName('tpAmb')->item(0);
        $this->assertNotNull($tpAmb);
        $this->assertEquals('1', $tpAmb->textContent);
    }

    public function test_s2200_birth_date_format_is_iso(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $dtNascto = $doc->getElementsByTagName('dtNascto')->item(0);
        $this->assertNotNull($dtNascto);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dtNascto->textContent);
        $this->assertEquals('1990-05-15', $dtNascto->textContent);
    }

    public function test_s2299_xml_has_correct_node_hierarchy(): void
    {
        $rescission = $this->createRescission();
        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $root = $doc->documentElement;
        $this->assertEquals('eSocial', $root->localName);

        $evtDeslig = $root->getElementsByTagName('evtDeslig')->item(0);
        $this->assertNotNull($evtDeslig);

        $this->assertNotNull($evtDeslig->getElementsByTagName('ideEvento')->item(0));
        $this->assertNotNull($evtDeslig->getElementsByTagName('ideEmpregador')->item(0));
        $this->assertNotNull($evtDeslig->getElementsByTagName('ideVinculo')->item(0));
        $this->assertNotNull($evtDeslig->getElementsByTagName('infoDeslig')->item(0));
    }

    public function test_s2299_contains_motivo_desligamento(): void
    {
        $rescission = $this->createRescission();
        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $mtvDeslig = $doc->getElementsByTagName('mtvDeslig')->item(0);
        $this->assertNotNull($mtvDeslig);
        // sem_justa_causa maps to '02'
        $this->assertEquals('02', $mtvDeslig->textContent);
    }

    public function test_s2299_contains_verbas_rescisorias(): void
    {
        $rescission = $this->createRescission();
        $event = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $verbasResc = $doc->getElementsByTagName('verbasResc')->item(0);
        $this->assertNotNull($verbasResc, 'S-2299 must contain verbasResc');

        $dmDev = $verbasResc->getElementsByTagName('dmDev')->item(0);
        $this->assertNotNull($dmDev, 'verbasResc must contain dmDev');
    }

    public function test_s1200_xml_has_correct_node_hierarchy(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03',
            'status' => 'calculated',
        ]);

        $event = $this->service->generateEvent('S-1200', $payroll, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $evtRemun = $doc->getElementsByTagName('evtRemun')->item(0);
        $this->assertNotNull($evtRemun);

        $this->assertNotNull($evtRemun->getElementsByTagName('ideEvento')->item(0));
        $this->assertNotNull($evtRemun->getElementsByTagName('ideEmpregador')->item(0));
        $this->assertNotNull($evtRemun->getElementsByTagName('dmDev')->item(0));
    }

    public function test_s1200_ide_evento_contains_per_apur(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03',
            'status' => 'calculated',
        ]);

        $event = $this->service->generateEvent('S-1200', $payroll, $this->tenant->id);

        $doc = new \DOMDocument;
        $doc->loadXML($event->xml_content);

        $perApur = $doc->getElementsByTagName('perApur')->item(0);
        $this->assertNotNull($perApur);
        $this->assertEquals('2026-03', $perApur->textContent);

        $indApuracao = $doc->getElementsByTagName('indApuracao')->item(0);
        $this->assertNotNull($indApuracao);
        $this->assertEquals('1', $indApuracao->textContent); // Mensal
    }

    public function test_esocial_namespace_is_correct_per_event_type(): void
    {
        $event = $this->service->generateEvent('S-2200', $this->user, $this->tenant->id);
        $this->assertStringContainsString('esocial.gov.br/schema/evt/evtAdmissao', $event->xml_content);

        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03',
            'status' => 'calculated',
        ]);
        $event1200 = $this->service->generateEvent('S-1200', $payroll, $this->tenant->id);
        $this->assertStringContainsString('esocial.gov.br/schema/evt/evtRemun', $event1200->xml_content);

        $rescission = $this->createRescission();
        $event2299 = $this->service->generateEvent('S-2299', $rescission, $this->tenant->id);
        $this->assertStringContainsString('esocial.gov.br/schema/evt/evtDeslig', $event2299->xml_content);
    }

    // ═══════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════

    private function createRescission(): Rescission
    {
        // Seed tax brackets for RescissionService
        $year = 2026;
        if (InssBracket::where('year', $year)->count() === 0) {
            InssBracket::create(['year' => $year, 'min_salary' => 0, 'max_salary' => 1518.00, 'rate' => 7.50, 'deduction' => 0]);
            InssBracket::create(['year' => $year, 'min_salary' => 1518.00, 'max_salary' => 2793.88, 'rate' => 9.00, 'deduction' => 0]);
            InssBracket::create(['year' => $year, 'min_salary' => 2793.88, 'max_salary' => 4190.83, 'rate' => 12.00, 'deduction' => 0]);
            InssBracket::create(['year' => $year, 'min_salary' => 4190.83, 'max_salary' => 8157.41, 'rate' => 14.00, 'deduction' => 0]);
        }
        if (IrrfBracket::where('year', $year)->count() === 0) {
            IrrfBracket::create(['year' => $year, 'min_base' => 0, 'max_base' => 2259.20, 'rate' => 0, 'deduction' => 0]);
            IrrfBracket::create(['year' => $year, 'min_base' => 2259.21, 'max_base' => 999999.99, 'rate' => 7.50, 'deduction' => 169.44]);
        }

        $rescissionService = app(RescissionService::class);

        return $rescissionService->calculate(
            $this->user,
            Rescission::TYPE_SEM_JUSTA_CAUSA,
            Carbon::parse('2026-03-18')
        );
    }
}
