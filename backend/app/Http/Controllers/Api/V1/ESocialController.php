<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ESocial\ExcludeEventRequest;
use App\Http\Requests\ESocial\GenerateEventRequest;
use App\Http\Requests\ESocial\SendBatchRequest;
use App\Http\Requests\ESocial\StoreCertificateRequest;
use App\Models\ESocialCertificate;
use App\Models\ESocialEvent;
use App\Services\ESocialService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ESocialController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(private ESocialService $esocialService) {}

    /**
     * List eSocial events (filterable by type, status, date range).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ESocialEvent::query()
                ->orderByDesc('created_at');

            if ($request->filled('event_type')) {
                $query->forType($request->input('event_type'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('batch_id')) {
                $query->forBatch($request->input('batch_id'));
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            if ($request->filled('environment')) {
                $query->where('environment', $request->input('environment'));
            }

            return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
        } catch (\Exception $e) {
            Log::error('ESocial index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar eventos eSocial.', 500);
        }
    }

    /**
     * Generate event for a specific entity.
     */
    public function generate(GenerateEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $tenantId = $this->resolvedTenantId();
            $modelClass = $validated['related_type'];

            /** @var Model|null $related */
            $related = $modelClass::where('id', $validated['related_id'])->first();

            if (! $related) {
                return ApiResponse::message('Entidade relacionada não encontrada.', 404);
            }

            // Check tenant ownership for tenant-scoped models
            if (method_exists($related, 'getAttribute') && $related->getAttribute('tenant_id')) {
                if ((int) $related->getAttribute('tenant_id') !== $tenantId) {
                    return ApiResponse::message('Entidade relacionada não encontrada.', 404);
                }
            }

            $event = $this->esocialService->generateEvent(
                $validated['event_type'],
                $related,
                $tenantId
            );

            // Mark as pending (ready to send) after successful generation
            $event->update(['status' => 'pending']);

            return ApiResponse::data($event, 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('ESocial generate failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::message('Erro ao gerar evento eSocial.', 500);
        }
    }

    /**
     * Show event details including XML.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $event = ESocialEvent::find($id);

            if (! $event) {
                return ApiResponse::message('Evento não encontrado.', 404);
            }

            if ($denied = $this->ensureTenantOwnership($event, 'Evento')) {
                return $denied;
            }

            return ApiResponse::data($event);
        } catch (\Exception $e) {
            Log::error('ESocial show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar evento eSocial.', 500);
        }
    }

    /**
     * Send batch of events.
     */
    public function sendBatch(SendBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $tenantId = $this->resolvedTenantId();

            // Verify all events belong to this tenant and are pending
            $events = ESocialEvent::whereIn('id', $validated['event_ids'])
                ->where('tenant_id', $tenantId)
                ->get();

            if ($events->count() !== count($validated['event_ids'])) {
                return ApiResponse::message('Alguns eventos não foram encontrados ou não pertencem a este tenant.', 422);
            }

            $nonPending = $events->where('status', '!=', 'pending');
            if ($nonPending->isNotEmpty()) {
                return ApiResponse::message('Todos os eventos devem estar com status pendente para envio.', 422);
            }

            $batchId = $this->esocialService->sendBatch($validated['event_ids']);

            return ApiResponse::data([
                'batch_id' => $batchId,
                'events_sent' => count($validated['event_ids']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('ESocial sendBatch failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao enviar lote eSocial.', 500);
        }
    }

    /**
     * Check batch status.
     */
    public function checkBatch(string $batchId): JsonResponse
    {
        try {
            $result = $this->esocialService->checkBatchStatus($batchId);

            if ($result['total'] === 0) {
                return ApiResponse::message('Lote não encontrado.', 404);
            }

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            Log::error('ESocial checkBatch failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao consultar lote eSocial.', 500);
        }
    }

    /**
     * List certificates.
     */
    public function certificates(Request $request): JsonResponse
    {
        try {
            $certs = ESocialCertificate::query()
                ->orderByDesc('created_at')
                ->get();

            return ApiResponse::data($certs);
        } catch (\Exception $e) {
            Log::error('ESocial certificates failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar certificados.', 500);
        }
    }

    /**
     * Upload certificate.
     */
    public function storeCertificate(StoreCertificateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $tenantId = $this->resolvedTenantId();

            // Store certificate file
            $path = $request->file('certificate')->store("esocial/certificates/{$tenantId}", 'local');

            // Deactivate previous certificates
            ESocialCertificate::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $cert = ESocialCertificate::create([
                'tenant_id' => $tenantId,
                'certificate_path' => $path,
                'certificate_password_encrypted' => Crypt::encryptString($validated['password']),
                'serial_number' => $validated['serial_number'] ?? null,
                'issuer' => $validated['issuer'] ?? null,
                'valid_from' => $validated['valid_from'] ?? null,
                'valid_until' => $validated['valid_until'] ?? null,
                'is_active' => true,
            ]);

            return ApiResponse::data($cert, 201);
        } catch (\Exception $e) {
            Log::error('ESocial storeCertificate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao salvar certificado.', 500);
        }
    }

    /**
     * Exclude (S-3000) a previously accepted event.
     */
    public function excludeEvent(ExcludeEventRequest $request, int $id): JsonResponse
    {

        try {
            $event = $this->esocialService->generateExclusionEvent($id, $request->input('reason'));

            return ApiResponse::data($event, 201);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Evento original não encontrado.', 404);
        } catch (\Exception $e) {
            Log::error('ESocial excludeEvent failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar evento de exclusão.', 500);
        }
    }

    /**
     * Generate S-1010 rubric table event.
     */
    public function generateRubricTable(Request $request): JsonResponse
    {
        try {
            $event = $this->esocialService->generateRubricTable($request->user()->current_tenant_id);

            return ApiResponse::data($event, 201);
        } catch (\Exception $e) {
            Log::error('ESocial generateRubricTable failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar tabela de rubricas.', 500);
        }
    }

    /**
     * Retry a single rejected event.
     */
    public function retryEvent(int $id): JsonResponse
    {
        try {
            $event = ESocialEvent::find($id);

            if (! $event) {
                return ApiResponse::message('Evento não encontrado.', 404);
            }

            if ($denied = $this->ensureTenantOwnership($event, 'Evento')) {
                return $denied;
            }

            $retried = $this->esocialService->retryEvent($id);

            return ApiResponse::data($retried);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('ESocial retryEvent failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao reprocessar evento.', 500);
        }
    }

    /**
     * Retry all eligible failed events.
     */
    public function retryAll(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->resolvedTenantId();
            $result = $this->esocialService->retryFailedEvents($tenantId);

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            Log::error('ESocial retryAll failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao reprocessar eventos.', 500);
        }
    }

    /**
     * Generate S-1000 (Informações do Empregador) event.
     */
    public function generateS1000(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->resolvedTenantId();
            $event = $this->esocialService->generateS1000($tenantId);
            $event->update(['status' => 'pending']);

            return ApiResponse::data($event, 201);
        } catch (\Exception $e) {
            Log::error('ESocial generateS1000 failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar evento S-1000.', 500);
        }
    }

    /**
     * Dashboard summary statistics.
     */
    public function dashboard(): JsonResponse
    {
        try {
            $pending = ESocialEvent::pending()->count();
            $sent = ESocialEvent::sent()->count();
            $accepted = ESocialEvent::accepted()->count();
            $rejected = ESocialEvent::rejected()->count();

            $recentEvents = ESocialEvent::query()
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'event_type', 'status', 'created_at', 'sent_at', 'response_at', 'error_message']);

            $byType = ESocialEvent::query()
                ->selectRaw('event_type, count(*) as total')
                ->groupBy('event_type')
                ->pluck('total', 'event_type');

            $activeCert = ESocialCertificate::active()->first();

            return ApiResponse::data([
                'counts' => [
                    'pending' => $pending,
                    'sent' => $sent,
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'total' => $pending + $sent + $accepted + $rejected,
                ],
                'by_type' => $byType,
                'recent_events' => $recentEvents,
                'certificate' => $activeCert ? [
                    'id' => $activeCert->id,
                    'serial_number' => $activeCert->serial_number,
                    'issuer' => $activeCert->issuer,
                    'valid_until' => $activeCert->valid_until?->toISOString(),
                    'is_expired' => $activeCert->is_expired,
                    'is_active' => $activeCert->is_active,
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('ESocial dashboard failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar dashboard eSocial.', 500);
        }
    }
}
