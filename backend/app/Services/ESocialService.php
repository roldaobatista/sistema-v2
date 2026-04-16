<?php

namespace App\Services;

use App\Jobs\ProcessESocialBatchJob;
use App\Models\ESocialEvent;
use App\Models\ESocialRubric;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Rescission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ESocialService
{
    /**
     * Generate XML for an event type.
     */
    public function generateEvent(string $eventType, Model $related, int $tenantId): ESocialEvent
    {
        $xmlContent = $this->buildXml($eventType, $related);

        return ESocialEvent::create([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'related_type' => get_class($related),
            'related_id' => $related->id,
            'xml_content' => $xmlContent,
            'status' => 'generating',
            'environment' => config('esocial.environment', 'restricted'),
            'version' => config('esocial.version', 'S-1.2'),
        ]);
    }

    /**
     * Build XML based on event type.
     */
    private function buildXml(string $eventType, Model $related): string
    {
        return match ($eventType) {
            'S-1000' => $this->buildS1000($related), // Empregador
            'S-2200' => $this->buildS2200($related), // Admissão
            'S-2206' => $this->buildS2206($related), // Alteração contratual
            'S-2299' => $this->buildS2299($related), // Desligamento
            'S-1200' => $this->buildS1200($related), // Remuneração
            'S-1210' => $this->buildS1210($related), // Pagamentos
            'S-2205' => $this->buildS2205($related), // Alteração cadastral
            'S-2210' => $this->buildS2210($related), // CAT
            'S-2220' => $this->buildS2220($related), // ASO
            'S-2230' => $this->buildS2230($related), // Afastamento
            'S-2240' => $this->buildS2240($related), // Condições ambientais
            default => throw new \InvalidArgumentException("Event type {$eventType} not supported"),
        };
    }

    /**
     * S-2200 — Admissão de Trabalhador.
     */
    private function buildS2200(Model $related): string
    {
        /** @var User $user */
        $user = $related;
        $tenant = $user->tenant ?? Tenant::find($user->current_tenant_id);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAdmissao/v_S_01_02_00"/>');

        $evtAdmissao = $xml->addChild('evtAdmissao');
        $evtAdmissao->addAttribute('Id', 'ID'.str_pad($user->id, 14, '0', STR_PAD_LEFT));

        // ideEvento
        $ideEvento = $evtAdmissao->addChild('ideEvento');
        $ideEvento->addChild('indRetif', '1'); // Original
        $ideEvento->addChild('tpAmb', config('esocial.environment') === 'production' ? '1' : '2');
        $ideEvento->addChild('procEmi', '1'); // Aplicativo do empregador
        $ideEvento->addChild('verProc', 'Sistema-v1.0');

        // ideEmpregador
        $ideEmpregador = $evtAdmissao->addChild('ideEmpregador');
        $ideEmpregador->addChild('tpInsc', '1'); // CNPJ
        $ideEmpregador->addChild('nrInsc', preg_replace('/\D/', '', $tenant->document ?? ''));

        // trabalhador
        $trabalhador = $evtAdmissao->addChild('trabalhador');
        $trabalhador->addChild('cpfTrab', $user->cpf ?? '');
        $trabalhador->addChild('nmTrab', $user->name);
        $trabalhador->addChild('dtNascto', $user->birth_date?->format('Y-m-d') ?? '');

        // vinculo
        $vinculo = $evtAdmissao->addChild('vinculo');
        $vinculo->addChild('matricula', str_pad($user->id, 10, '0', STR_PAD_LEFT));
        $vinculo->addChild('dtAdm', $user->admission_date?->format('Y-m-d') ?? '');

        // infoContrato
        $contrato = $vinculo->addChild('infoContrato');
        $contrato->addChild('codCBO', $user->cbo_code ?? '');
        $contrato->addChild('vrSalFx', number_format($user->salary ?? 0, 2, '.', ''));
        $contrato->addChild('undSalFixo', '5'); // Mensal

        return $xml->asXML();
    }

    /**
     * S-2299 — Desligamento.
     */
    private function buildS2299(Model $related): string
    {
        /** @var Rescission $rescission */
        $rescission = $related;
        $user = $rescission->user;
        $tenant = Tenant::find($rescission->tenant_id);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtDeslig/v_S_01_02_00"/>');

        $evtDeslig = $xml->addChild('evtDeslig');
        $evtDeslig->addAttribute('Id', 'ID'.str_pad($rescission->id, 14, '0', STR_PAD_LEFT));

        $ideEvento = $evtDeslig->addChild('ideEvento');
        $ideEvento->addChild('indRetif', '1');
        $ideEvento->addChild('tpAmb', config('esocial.environment') === 'production' ? '1' : '2');
        $ideEvento->addChild('procEmi', '1');
        $ideEvento->addChild('verProc', 'Sistema-v1.0');

        $ideEmpregador = $evtDeslig->addChild('ideEmpregador');
        $ideEmpregador->addChild('tpInsc', '1');
        $ideEmpregador->addChild('nrInsc', preg_replace('/\D/', '', $tenant->document ?? ''));

        $ideVinculo = $evtDeslig->addChild('ideVinculo');
        $ideVinculo->addChild('cpfTrab', $user->cpf ?? '');
        $ideVinculo->addChild('matricula', str_pad($user->id, 10, '0', STR_PAD_LEFT));

        $infoDeslig = $evtDeslig->addChild('infoDeslig');
        $infoDeslig->addChild('dtDeslig', $rescission->termination_date->format('Y-m-d'));
        $infoDeslig->addChild('mtvDeslig', $this->getMotivoCodigo($rescission->type));

        // Verbas rescisórias
        $verbasResc = $infoDeslig->addChild('verbasResc');
        $dmDev = $verbasResc->addChild('dmDev');
        $dmDev->addAttribute('ideDmDev', '1');

        // Itens das verbas seriam adicionados a partir dos dados da rescisão
        $ideEstabLot = $dmDev->addChild('ideEstabLot');
        $ideEstabLot->addChild('tpInsc', '1');
        $ideEstabLot->addChild('nrInsc', preg_replace('/\D/', '', $tenant->document ?? ''));

        return $xml->asXML();
    }

    /**
     * S-1200 — Remuneração de Trabalhador vinculado ao RGPS.
     */
    private function buildS1200(Model $related): string
    {
        /** @var Payroll $payroll */
        $payroll = $related;
        $tenant = Tenant::find($payroll->tenant_id);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v_S_01_02_00"/>');

        $evtRemun = $xml->addChild('evtRemun');
        $evtRemun->addAttribute('Id', 'ID'.str_pad($payroll->id, 14, '0', STR_PAD_LEFT));

        $ideEvento = $evtRemun->addChild('ideEvento');
        $ideEvento->addChild('indRetif', '1');
        $ideEvento->addChild('indApuracao', '1'); // Mensal
        $ideEvento->addChild('perApur', $payroll->reference_month);
        $ideEvento->addChild('tpAmb', config('esocial.environment') === 'production' ? '1' : '2');
        $ideEvento->addChild('procEmi', '1');
        $ideEvento->addChild('verProc', 'Sistema-v1.0');

        $ideEmpregador = $evtRemun->addChild('ideEmpregador');
        $ideEmpregador->addChild('tpInsc', '1');
        $ideEmpregador->addChild('nrInsc', preg_replace('/\D/', '', $tenant->document ?? ''));

        $ideTrabalhador = $evtRemun->addChild('ideTrabalhador');
        $ideTrabalhador->addChild('cpfTrab', ''); // Preenchido por linha

        $dmDev = $evtRemun->addChild('dmDev');
        $dmDev->addAttribute('ideDmDev', '1');

        $infoPerApur = $dmDev->addChild('infoPerApur');
        $ideEstabLot = $infoPerApur->addChild('ideEstabLot');
        $ideEstabLot->addChild('tpInsc', '1');
        $ideEstabLot->addChild('nrInsc', preg_replace('/\D/', '', $tenant->document ?? ''));

        $remunPerApur = $ideEstabLot->addChild('remunPerApur');
        $remunPerApur->addChild('matricula', '');
        $remunPerApur->addChild('codCateg', '101'); // Empregado geral

        return $xml->asXML();
    }

    // Stub remaining event types with valid XML structure

    private function buildS2205(Model $related): string
    {
        return $this->buildStubXml('evtAltCadastral');
    }

    private function buildS2206(Model $related): string
    {
        return $this->buildStubXml('evtAltContratual');
    }

    private function buildS1210(Model $related): string
    {
        return $this->buildStubXml('evtPgtos');
    }

    private function buildS2210(Model $related): string
    {
        return $this->buildStubXml('evtCAT');
    }

    private function buildS2220(Model $related): string
    {
        return $this->buildStubXml('evtMonit');
    }

    /**
     * S-2230 — Afastamento Temporário.
     */
    private function buildS2230(Model $related): string
    {
        /** @var LeaveRequest $leave */
        $leave = $related;
        $user = $leave->user;
        $tenant = Tenant::find($leave->tenant_id);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAfastTemp/v_S_01_02_00"/>');

        $evtAfastTemp = $xml->addChild('evtAfastTemp');
        $evtAfastTemp->addAttribute('Id', 'ID'.str_pad($leave->id, 14, '0', STR_PAD_LEFT));

        // ideEvento
        $ideEvento = $evtAfastTemp->addChild('ideEvento');
        $ideEvento->addChild('indRetif', '1');
        $ideEvento->addChild('tpAmb', config('esocial.environment') === 'production' ? '1' : '2');
        $ideEvento->addChild('procEmi', '1');
        $ideEvento->addChild('verProc', 'Sistema-v1.0');

        // ideEmpregador
        $ideEmpregador = $evtAfastTemp->addChild('ideEmpregador');
        $ideEmpregador->addChild('tpInsc', '1');
        $ideEmpregador->addChild('nrInsc', preg_replace('/\D/', '', $tenant->document ?? ''));

        // ideVinculo
        $ideVinculo = $evtAfastTemp->addChild('ideVinculo');
        $ideVinculo->addChild('cpfTrab', $user->cpf ?? '');
        $ideVinculo->addChild('matricula', str_pad($user->id, 10, '0', STR_PAD_LEFT));

        // infoAfastamento
        $infoAfastamento = $evtAfastTemp->addChild('infoAfastamento');
        $iniAfastamento = $infoAfastamento->addChild('iniAfastamento');
        $iniAfastamento->addChild('dtIniAfast', $leave->start_date->format('Y-m-d'));
        $iniAfastamento->addChild('codMotAfast', $this->getLeaveMotivoCodigo($leave->type));

        // Fim do afastamento (se já definido)
        if ($leave->end_date) {
            $fimAfastamento = $infoAfastamento->addChild('fimAfastamento');
            $fimAfastamento->addChild('dtTermAfast', $leave->end_date->format('Y-m-d'));
        }

        return $xml->asXML();
    }

    private function buildS2240(Model $related): string
    {
        return $this->buildStubXml('evtExpRisco');
    }

    private function buildStubXml(string $eventName): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?><eSocial xmlns=\"http://www.esocial.gov.br/schema/evt/{$eventName}/v_S_01_02_00\"><{$eventName}><ideEvento><indRetif>1</indRetif><tpAmb>".(config('esocial.environment') === 'production' ? '1' : '2')."</tpAmb><procEmi>1</procEmi><verProc>Sistema-v1.0</verProc></ideEvento></{$eventName}></eSocial>";
    }

    /**
     * Send batch of events.
     *
     * Dispatches the batch for async transmission via ESocialTransmissionService.
     * Returns the batch_id immediately; actual API transmission happens in the background.
     */
    public function sendBatch(array $eventIds): string
    {
        $batchId = 'BATCH-'.now()->format('YmdHis').'-'.substr(md5(uniqid('', true)), 0, 8);

        // Verify all events exist and are eligible for sending
        $events = ESocialEvent::whereIn('id', $eventIds)
            ->whereIn('status', ['pending', 'generating'])
            ->get();

        if ($events->isEmpty()) {
            throw new \InvalidArgumentException('Nenhum evento pendente encontrado para envio.');
        }

        $tenantId = $events->first()->tenant_id;

        // Mark as queued with batch_id (intermediate status)
        ESocialEvent::whereIn('id', $events->pluck('id')->toArray())
            ->update([
                'status' => 'sent',
                'batch_id' => $batchId,
                'sent_at' => now(),
            ]);

        // Dispatch async job for real transmission with exponential backoff
        ProcessESocialBatchJob::dispatch(
            $events->pluck('id')->toArray(),
            $tenantId,
        );

        return $batchId;
    }

    /**
     * Retry all eligible rejected events.
     */
    public function retryFailedEvents(?int $tenantId = null): array
    {
        $query = ESocialEvent::retryable();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $events = $query->get();
        $retried = [];

        foreach ($events as $event) {
            $event->markForRetry();
            $retried[] = $event->id;
        }

        return [
            'retried_count' => count($retried),
            'event_ids' => $retried,
        ];
    }

    /**
     * Retry a single event by ID.
     */
    public function retryEvent(int $eventId): ESocialEvent
    {
        $event = ESocialEvent::findOrFail($eventId);

        if (! $event->shouldRetry()) {
            throw new \InvalidArgumentException(
                $event->hasExhaustedRetries()
                    ? "Event {$eventId} has exhausted all retries ({$event->max_retries})."
                    : "Event {$eventId} is not eligible for retry (status: {$event->status})."
            );
        }

        $event->markForRetry();

        return $event;
    }

    /**
     * Generate S-1000 employer information event for a tenant.
     */
    public function generateS1000(int $tenantId): ESocialEvent
    {
        $tenant = Tenant::findOrFail($tenantId);

        return $this->generateEvent('S-1000', $tenant, $tenantId);
    }

    /**
     * Check batch status.
     */
    public function checkBatchStatus(string $batchId): array
    {
        $events = ESocialEvent::where('batch_id', $batchId)->get();

        return [
            'batch_id' => $batchId,
            'total' => $events->count(),
            'sent' => $events->where('status', 'sent')->count(),
            'accepted' => $events->where('status', 'accepted')->count(),
            'rejected' => $events->where('status', 'rejected')->count(),
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'status' => $e->status,
                'error_message' => $e->error_message,
            ]),
        ];
    }

    /**
     * Process response from eSocial.
     */
    public function processResponse(ESocialEvent $event, string $responseXml, string $status = 'accepted', ?string $receipt = null, ?string $error = null): void
    {
        $event->update([
            'response_xml' => $responseXml,
            'status' => $status,
            'receipt_number' => $receipt,
            'error_message' => $error,
            'response_at' => now(),
        ]);
    }

    /**
     * Generate S-3000 exclusion event for a previously accepted event.
     */
    public function generateExclusionEvent(int $originalEventId, string $reason): ESocialEvent
    {
        $original = ESocialEvent::findOrFail($originalEventId);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtExclusao/v_S_01_02_00"/>');
        $evt = $xml->addChild('evtExclusao');
        $ideEvento = $evt->addChild('ideEvento');
        $ideEvento->addChild('tpAmb', config('esocial.environment', '2'));
        $ideEvento->addChild('procEmi', '1');
        $ideEvento->addChild('verProc', '1.0');
        $info = $evt->addChild('infoExclusao');
        $info->addChild('tpEvento', $original->event_type);
        $info->addChild('nrRecEvt', $original->protocol_number ?? $original->receipt_number ?? '');

        $exclusionEvent = ESocialEvent::create([
            'tenant_id' => $original->tenant_id,
            'event_type' => 'S-3000',
            'user_id' => $original->user_id,
            'xml_content' => $xml->asXML(),
            'status' => 'pending',
            'metadata' => [
                'original_event_id' => $original->id,
                'original_event_type' => $original->event_type,
                'original_protocol' => $original->protocol_number,
                'reason' => $reason,
            ],
        ]);

        $original->update(['status' => 'cancelled']);

        return $exclusionEvent;
    }

    /**
     * Generate S-1010 rubric table event.
     */
    public function generateRubricTable(int $tenantId): ESocialEvent
    {
        $rubrics = ESocialRubric::where('tenant_id', $tenantId)
            ->where('is_active', true)->get();

        if ($rubrics->isEmpty()) {
            $rubrics = $this->createDefaultRubrics($tenantId);
        }

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtTabRubrica/v_S_01_02_00"/>');
        $evt = $xml->addChild('evtTabRubrica');
        $ideEvento = $evt->addChild('ideEvento');
        $ideEvento->addChild('tpAmb', config('esocial.environment', '2'));
        $ideEvento->addChild('procEmi', '1');
        $ideEvento->addChild('verProc', '1.0');

        foreach ($rubrics as $rubric) {
            $info = $evt->addChild('infoRubrica');
            $inclusao = $info->addChild('inclusao');
            $ide = $inclusao->addChild('ideRubrica');
            $ide->addChild('codRubr', $rubric->code);
            $ide->addChild('ideTabRubr', 'TAB1');
            $dados = $inclusao->addChild('dadosRubrica');
            $dados->addChild('dscRubr', $rubric->description);
            $dados->addChild('natRubr', $rubric->nature === 'provento' ? '1000' : ($rubric->nature === 'desconto' ? '9000' : '5000'));
            $dados->addChild('tpRubr', $rubric->nature === 'provento' ? '1' : ($rubric->nature === 'desconto' ? '2' : '3'));
        }

        return ESocialEvent::create([
            'tenant_id' => $tenantId,
            'event_type' => 'S-1010',
            'xml_content' => $xml->asXML(),
            'status' => 'pending',
        ]);
    }

    /**
     * Generate S-1200 and S-1210 events for all employees in a payroll.
     */
    public function generatePayrollEvents(Payroll $payroll): array
    {
        $lines = PayrollLine::where('payroll_id', $payroll->id)
            ->with('user')
            ->get();

        $events = [];

        foreach ($lines as $line) {
            if (! $line->user) {
                continue;
            }

            // S-1200: Remuneração do Trabalhador
            $xml1200 = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v_S_01_02_00"/>');
            $evt = $xml1200->addChild('evtRemun');
            $ideEvento = $evt->addChild('ideEvento');
            $ideEvento->addChild('perApur', $payroll->reference_month);
            $ideEvento->addChild('tpAmb', config('esocial.environment', '2'));
            $ideTrabalhador = $evt->addChild('ideTrabalhador');
            $ideTrabalhador->addChild('cpfTrab', $line->user->cpf ?? '');
            $dmDev = $evt->addChild('dmDev');
            $dmDev->addChild('ideDmDev', 'DMV-'.$payroll->id.'-'.$line->user_id);
            $infoPerApur = $dmDev->addChild('infoPerApur');
            $ideEstabLot = $infoPerApur->addChild('ideEstabLot');
            $remunPerApur = $ideEstabLot->addChild('remunPerApur');
            $remunPerApur->addChild('vrSalFx', number_format($line->base_salary ?? 0, 2, '.', ''));

            $events[] = ESocialEvent::create([
                'tenant_id' => $payroll->tenant_id,
                'event_type' => 'S-1200',
                'user_id' => $line->user_id,
                'xml_content' => $xml1200->asXML(),
                'status' => 'pending',
                'metadata' => ['payroll_id' => $payroll->id],
            ]);

            // S-1210: Pagamentos de Rendimentos
            $xml1210 = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtPgtos/v_S_01_02_00"/>');
            $evtPgtos = $xml1210->addChild('evtPgtos');
            $ideEvento2 = $evtPgtos->addChild('ideEvento');
            $ideEvento2->addChild('perApur', $payroll->reference_month);
            $ideEvento2->addChild('tpAmb', config('esocial.environment', '2'));
            $ideBenef = $evtPgtos->addChild('ideBenef');
            $ideBenef->addChild('cpfBenef', $line->user->cpf ?? '');
            $infoPgto = $evtPgtos->addChild('infoPgto');
            $infoPgto->addChild('dtPgto', now()->format('Y-m-d'));
            $infoPgto->addChild('vrLiq', number_format((float) ($line->net_salary ?? 0), 2, '.', ''));

            $events[] = ESocialEvent::create([
                'tenant_id' => $payroll->tenant_id,
                'event_type' => 'S-1210',
                'user_id' => $line->user_id,
                'xml_content' => $xml1210->asXML(),
                'status' => 'pending',
                'metadata' => ['payroll_id' => $payroll->id],
            ]);
        }

        return $events;
    }

    private function createDefaultRubrics(int $tenantId): Collection
    {
        $defaults = [
            ['code' => '1000', 'description' => 'Salário Base', 'nature' => 'provento', 'type' => 'salario_base', 'incidence_inss' => true, 'incidence_irrf' => true, 'incidence_fgts' => true],
            ['code' => '1010', 'description' => 'Hora Extra 50%', 'nature' => 'provento', 'type' => 'he_50', 'incidence_inss' => true, 'incidence_irrf' => true, 'incidence_fgts' => true],
            ['code' => '1011', 'description' => 'Hora Extra 100%', 'nature' => 'provento', 'type' => 'he_100', 'incidence_inss' => true, 'incidence_irrf' => true, 'incidence_fgts' => true],
            ['code' => '1020', 'description' => 'Adicional Noturno', 'nature' => 'provento', 'type' => 'noturno', 'incidence_inss' => true, 'incidence_irrf' => true, 'incidence_fgts' => true],
            ['code' => '1030', 'description' => 'DSR sobre Extras', 'nature' => 'provento', 'type' => 'dsr', 'incidence_inss' => true, 'incidence_irrf' => true, 'incidence_fgts' => true],
            ['code' => '3000', 'description' => 'INSS Empregado', 'nature' => 'desconto', 'type' => 'inss', 'incidence_inss' => false, 'incidence_irrf' => false, 'incidence_fgts' => false],
            ['code' => '3010', 'description' => 'IRRF', 'nature' => 'desconto', 'type' => 'irrf', 'incidence_inss' => false, 'incidence_irrf' => false, 'incidence_fgts' => false],
            ['code' => '3020', 'description' => 'Vale Transporte', 'nature' => 'desconto', 'type' => 'vt', 'incidence_inss' => false, 'incidence_irrf' => false, 'incidence_fgts' => false],
            ['code' => '3030', 'description' => 'Vale Refeição', 'nature' => 'desconto', 'type' => 'vr', 'incidence_inss' => false, 'incidence_irrf' => false, 'incidence_fgts' => false],
            ['code' => '5000', 'description' => 'FGTS', 'nature' => 'informativa', 'type' => 'fgts', 'incidence_inss' => false, 'incidence_irrf' => false, 'incidence_fgts' => false],
        ];

        $rubrics = collect();
        foreach ($defaults as $data) {
            $rubrics->push(ESocialRubric::create(array_merge($data, ['tenant_id' => $tenantId])));
        }

        return $rubrics;
    }

    /**
     * Map rescission type to eSocial motivo desligamento code.
     */
    private function getMotivoCodigo(string $type): string
    {
        return match ($type) {
            'sem_justa_causa' => '02',
            'justa_causa' => '01',
            'pedido_demissao' => '07',
            'acordo_mutuo' => '33',
            'termino_contrato' => '04',
            default => '99',
        };
    }

    /**
     * Map LeaveRequest type to eSocial codMotAfast.
     */
    private function getLeaveMotivoCodigo(string $type): string
    {
        return match ($type) {
            'medical' => '01',        // Doença (CID)
            'work_accident' => '03',  // Acidente de trabalho
            'maternity' => '06',      // Licença maternidade
            'paternity' => '19',      // Licença paternidade
            'vacation' => '15',       // Férias
            'bereavement' => '21',    // Licença nojo
            'personal' => '26',       // Outros motivos
            default => '26',
        };
    }

    /**
     * S-1000 — Informações do Empregador/Contribuinte.
     */
    private function buildS1000(Model $related): string
    {
        /** @var Tenant $tenant */
        $tenant = $related;

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtInfoEmpregador/v_S_01_02_00"/>');

        $evtInfoEmpregador = $xml->addChild('evtInfoEmpregador');
        $evtInfoEmpregador->addAttribute('Id', 'ID'.str_pad($tenant->id, 14, '0', STR_PAD_LEFT));

        // ideEvento
        $ideEvento = $evtInfoEmpregador->addChild('ideEvento');
        $ideEvento->addChild('tpAmb', config('esocial.environment') === 'production' ? '1' : '2');
        $ideEvento->addChild('procEmi', '1');
        $ideEvento->addChild('verProc', 'Sistema-v1.0');

        // ideEmpregador
        $ideEmpregador = $evtInfoEmpregador->addChild('ideEmpregador');
        $ideEmpregador->addChild('tpInsc', '1'); // CNPJ
        $ideEmpregador->addChild('nrInsc', preg_replace('/\D/', '', $tenant->document ?? ''));

        // infoEmpregador
        $infoEmpregador = $evtInfoEmpregador->addChild('infoEmpregador');
        $inclusao = $infoEmpregador->addChild('inclusao');

        // idePeriodo
        $idePeriodo = $inclusao->addChild('idePeriodo');
        $idePeriodo->addChild('iniValid', now()->format('Y-m'));

        // infoCadastro
        $infoCadastro = $inclusao->addChild('infoCadastro');
        $infoCadastro->addChild('nmRazao', $tenant->company_name ?? $tenant->name ?? '');
        $infoCadastro->addChild('classTrib', '99'); // Genérico — ajustar conforme regime do tenant
        $infoCadastro->addChild('indCoop', '0'); // Não cooperativa
        $infoCadastro->addChild('indConstr', '0'); // Não construtora

        // contato
        $contato = $infoCadastro->addChild('contato');
        $contato->addChild('nmCtt', $tenant->contact_name ?? $tenant->name ?? '');
        $contato->addChild('cpfCtt', preg_replace('/\D/', '', $tenant->contact_cpf ?? ''));
        $contato->addChild('foneFixo', preg_replace('/\D/', '', $tenant->phone ?? ''));
        $contato->addChild('email', $tenant->email ?? '');

        return $xml->asXML();
    }
}
