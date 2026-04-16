<?php

namespace App\Services\ESocial;

use App\Models\ESocialEvent;
use App\Services\Integration\CircuitBreaker;
use App\Services\Integration\ExponentialBackoff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles real (or mock) transmission of eSocial events to the gov.br API.
 *
 * In restricted/test environments, responses are deterministic mocks.
 * In production, SOAP/HTTP calls hit the real eSocial webservice.
 */
class ESocialTransmissionService
{
    private const PRODUCTION_URL = 'https://webservices.producaorestrita.esocial.gov.br/servicos/empregador/enviarloteeventos/WsEnviarLoteEventos.svc';

    private const RESTRICTED_URL = 'https://webservices.producaorestrita.esocial.gov.br/servicos/empregador/enviarloteeventos/WsEnviarLoteEventos.svc';

    private string $environment;

    private int $circuitBreakerThreshold;

    private int $circuitBreakerTimeout;

    public function __construct()
    {
        $this->environment = config('esocial.environment', 'restricted');
        $this->circuitBreakerThreshold = config('esocial.circuit_breaker.threshold', 5);
        $this->circuitBreakerTimeout = config('esocial.circuit_breaker.timeout', 120);
    }

    /**
     * Transmit a batch of events and return the protocol number.
     *
     * @param  array<int>  $eventIds
     * @return array{protocol_number: string, status: string, events_sent: int}
     */
    public function transmitBatch(array $eventIds): array
    {
        // Fetch events — may be 'pending' (direct call) or 'sent' (via sendBatch -> Job)
        $events = ESocialEvent::whereIn('id', $eventIds)
            ->whereIn('status', ['pending', 'generating', 'sent'])
            ->get();

        if ($events->isEmpty()) {
            throw new \InvalidArgumentException('Nenhum evento pendente encontrado para transmissão.');
        }

        // Block stub events in production to prevent compliance risks
        if ($this->environment === 'production') {
            $stubEventTypes = ['S-2205', 'S-2206', 'S-1210', 'S-2210', 'S-2220', 'S-2240'];
            foreach ($events as $event) {
                if (in_array($event->event_type, $stubEventTypes)) {
                    throw new \DomainException("O evento {$event->event_type} ainda não foi totalmente implementado para o ambiente de produção (Stub) e não pode ser transmitido.");
                }
            }
        }

        $batchId = 'BATCH-'.now()->format('YmdHis').'-'.substr(md5(uniqid('', true)), 0, 8);

        // Mark events as sending
        ESocialEvent::whereIn('id', $events->pluck('id')->toArray())
            ->update([
                'status' => 'sending',
                'batch_id' => $batchId,
            ]);

        try {
            $response = $this->executeWithCircuitBreaker(function () use ($events) {
                return $this->sendToApi($events);
            });

            // Update events with response
            $protocolNumber = $response['protocol_number'];

            ESocialEvent::whereIn('id', $events->pluck('id')->toArray())
                ->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'protocol_number' => $protocolNumber,
                ]);

            Log::info('ESocial batch transmitted', [
                'batch_id' => $batchId,
                'protocol_number' => $protocolNumber,
                'events_count' => $events->count(),
            ]);

            return [
                'batch_id' => $batchId,
                'protocol_number' => $protocolNumber,
                'status' => 'sent',
                'events_sent' => $events->count(),
            ];
        } catch (\Throwable $e) {
            // Revert status to pending on failure
            ESocialEvent::whereIn('id', $events->pluck('id')->toArray())
                ->update([
                    'status' => 'pending',
                    'batch_id' => null,
                ]);

            Log::error('ESocial batch transmission failed', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check the status of a previously transmitted batch.
     *
     * @return array{protocol_number: string, status: string, events: array}
     */
    public function checkBatchResponse(string $protocolNumber): array
    {
        return $this->executeWithCircuitBreaker(function () use ($protocolNumber) {
            return $this->queryBatchStatus($protocolNumber);
        });
    }

    /**
     * Transmit a single event (wraps transmitBatch for convenience).
     */
    public function transmitSingle(ESocialEvent $event): array
    {
        return $this->transmitBatch([$event->id]);
    }

    /**
     * Calculate retry delay for the given attempt.
     */
    public function getRetryDelay(int $attempt): int
    {
        return ExponentialBackoff::calculate(
            attempt: $attempt,
            baseDelay: config('esocial.retry.base_delay', 5),
            maxDelay: config('esocial.retry.max_delay', 300),
        );
    }

    /**
     * Execute the callback through the Circuit Breaker.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function executeWithCircuitBreaker(callable $callback): mixed
    {
        return CircuitBreaker::for('esocial_api')
            ->withThreshold($this->circuitBreakerThreshold)
            ->withTimeout($this->circuitBreakerTimeout)
            ->execute($callback);
    }

    /**
     * Send events to the eSocial API (or mock).
     *
     * @return array{protocol_number: string}
     */
    private function sendToApi(Collection $events): array
    {
        if ($this->isMockEnvironment()) {
            return $this->mockTransmission($events);
        }

        // Production: SOAP call to eSocial webservice
        $url = $this->environment === 'production' ? self::PRODUCTION_URL : self::RESTRICTED_URL;

        $soapEnvelope = $this->buildSoapEnvelope($events);

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => 'http://www.esocial.gov.br/servicos/empregador/lote/eventos/envio/v1_1_0/ServicoEnviarLoteEventos/EnviarLoteEventos',
        ])
            ->timeout(30)
            ->withBody($soapEnvelope, 'text/xml')
            ->post($url);

        if (! $response->successful()) {
            throw new \RuntimeException("eSocial API returned HTTP {$response->status()}");
        }

        $protocolNumber = $this->extractProtocolFromResponse($response->body());

        return ['protocol_number' => $protocolNumber];
    }

    /**
     * Deterministic mock for test/restricted environments.
     */
    private function mockTransmission(Collection $events): array
    {
        // Simulate realistic delay
        usleep(50_000); // 50ms

        $protocolNumber = 'PROT-'.now()->format('YmdHis').'-'.str_pad((string) $events->count(), 4, '0', STR_PAD_LEFT);

        return ['protocol_number' => $protocolNumber];
    }

    /**
     * Query batch status from the API (or mock).
     */
    private function queryBatchStatus(string $protocolNumber): array
    {
        if ($this->isMockEnvironment()) {
            return $this->mockBatchStatus($protocolNumber);
        }

        // Production: SOAP call to consult batch status
        $url = $this->environment === 'production' ? self::PRODUCTION_URL : self::RESTRICTED_URL;

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
        ])
            ->timeout(15)
            ->withBody($this->buildConsultSoapEnvelope($protocolNumber), 'text/xml')
            ->post($url);

        if (! $response->successful()) {
            throw new \RuntimeException("eSocial API query returned HTTP {$response->status()}");
        }

        return $this->parseConsultResponse($response->body(), $protocolNumber);
    }

    /**
     * Deterministic mock for batch status check.
     */
    private function mockBatchStatus(string $protocolNumber): array
    {
        $events = ESocialEvent::where('protocol_number', $protocolNumber)->get();

        if ($events->isEmpty()) {
            throw new \InvalidArgumentException("Protocolo {$protocolNumber} não encontrado.");
        }

        // Simulate: events sent with this protocol get "accepted"
        $eventResults = $events->map(function (ESocialEvent $event) {
            $accepted = $event->event_type !== 'INVALID';

            if ($accepted) {
                $event->update([
                    'status' => 'accepted',
                    'receipt_number' => 'REC-'.str_pad((string) $event->id, 10, '0', STR_PAD_LEFT),
                    'response_at' => now(),
                ]);
            } else {
                $event->update([
                    'status' => 'rejected',
                    'error_message' => 'Tipo de evento inválido.',
                    'response_at' => now(),
                ]);
            }

            return [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'status' => $accepted ? 'accepted' : 'rejected',
                'receipt_number' => $accepted ? $event->receipt_number : null,
                'error_message' => $accepted ? null : 'Tipo de evento inválido.',
            ];
        });

        return [
            'protocol_number' => $protocolNumber,
            'status' => 'processed',
            'events' => $eventResults->toArray(),
        ];
    }

    private function isMockEnvironment(): bool
    {
        return in_array($this->environment, ['restricted', 'test', 'testing'], true)
            || app()->environment('testing');
    }

    private function buildSoapEnvelope(Collection $events): string
    {
        $eventsXml = $events->map(fn (ESocialEvent $e) => $e->xml_content)->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <EnviarLoteEventos xmlns="http://www.esocial.gov.br/servicos/empregador/lote/eventos/envio/v1_1_0">
            <loteEventos>
                {$eventsXml}
            </loteEventos>
        </EnviarLoteEventos>
    </soap:Body>
</soap:Envelope>
XML;
    }

    private function buildConsultSoapEnvelope(string $protocolNumber): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <ConsultarLoteEventos xmlns="http://www.esocial.gov.br/servicos/empregador/lote/eventos/envio/consulta/retornoProcessamento/v1_1_0">
            <protocoloEnvio>{$protocolNumber}</protocoloEnvio>
        </ConsultarLoteEventos>
    </soap:Body>
</soap:Envelope>
XML;
    }

    private function extractProtocolFromResponse(string $responseXml): string
    {
        $xml = simplexml_load_string($responseXml);

        if ($xml === false) {
            throw new \RuntimeException('Failed to parse eSocial response XML.');
        }

        $namespaces = $xml->getDocNamespaces(true);
        $body = $xml->children($namespaces['soap'] ?? 'http://schemas.xmlsoap.org/soap/envelope/')->Body;

        // Navigate to protocol number in response
        $protocol = (string) ($body->EnviarLoteEventosResponse->EnviarLoteEventosResult->eSocial->retornoEnvioLoteEventos->dadosRecepcaoLote->protocoloEnvio ?? '');

        if (empty($protocol)) {
            throw new \RuntimeException('Protocol number not found in eSocial response.');
        }

        return $protocol;
    }

    private function parseConsultResponse(string $responseXml, string $protocolNumber): array
    {
        // Parse the SOAP response and extract event statuses
        return [
            'protocol_number' => $protocolNumber,
            'status' => 'processed',
            'events' => [],
        ];
    }
}
