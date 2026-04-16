<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CreateInteractiveProposalRequest;
use App\Http\Requests\Crm\RespondToProposalRequest;
use App\Models\CrmInteractiveProposal;
use App\Models\CrmMessage;
use App\Models\CrmTrackingEvent;
use App\Models\Quote;
use App\Services\QuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmProposalTrackingController extends Controller
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {}

    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    private function createTrackingEvent(
        int $tenantId, string $trackableType, int $trackableId,
        string $eventType, ?int $customerId = null, ?int $dealId = null, ?array $metadata = null
    ): void {
        CrmTrackingEvent::create([
            'tenant_id' => $tenantId, 'trackable_type' => $trackableType, 'trackable_id' => $trackableId,
            'customer_id' => $customerId, 'deal_id' => $dealId, 'event_type' => $eventType,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'metadata' => $metadata,
        ]);
    }

    // ── Interactive Proposals ─────────────────────────────

    public function interactiveProposals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.proposal.view'), 403);

        $query = CrmInteractiveProposal::where('tenant_id', $this->tenantId($request))
            ->with(['quote:id,quote_number,total,status', 'deal:id,title'])
            ->orderByDesc('created_at');

        if ($request->has('quote_id')) {
            $query->where('quote_id', (int) $request->input('quote_id'));
        }

        return ApiResponse::paginated($query->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function createInteractiveProposal(CreateInteractiveProposalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $proposal = CrmInteractiveProposal::create([...$data, 'tenant_id' => $this->tenantId($request)]);

        return ApiResponse::data($proposal, 201);
    }

    public function viewInteractiveProposal(string $token): JsonResponse
    {
        $proposal = CrmInteractiveProposal::where('token', $token)->with(['quote.items', 'deal:id,title'])->firstOrFail();

        if ($proposal->isExpired()) {
            if ($proposal->status !== CrmInteractiveProposal::STATUS_EXPIRED) {
                $proposal->update(['status' => CrmInteractiveProposal::STATUS_EXPIRED]);
            }

            return ApiResponse::message('Proposta expirada', 410);
        }

        $proposal->recordView();
        $proposal->loadMissing('quote:id,customer_id', 'deal:id,title');

        $this->createTrackingEvent(
            tenantId: $proposal->tenant_id, trackableType: CrmInteractiveProposal::class,
            trackableId: $proposal->id, eventType: 'proposal_viewed',
            customerId: $proposal->quote?->customer_id, dealId: $proposal->deal_id,
        );

        return ApiResponse::data($proposal);
    }

    public function respondToProposal(RespondToProposalRequest $request, string $token): JsonResponse
    {
        $proposal = CrmInteractiveProposal::where('token', $token)->firstOrFail();

        if ($proposal->isExpired()) {
            if ($proposal->status !== CrmInteractiveProposal::STATUS_EXPIRED) {
                $proposal->update(['status' => CrmInteractiveProposal::STATUS_EXPIRED]);
            }

            return ApiResponse::message('Proposta expirada', 410);
        }

        if (! $proposal->canReceiveResponse()) {
            return ApiResponse::message('Proposta ja respondida ou indisponivel.', 422);
        }

        $data = $request->validated();

        try {
            $message = DB::transaction(function () use ($data, $token) {
                $proposal = CrmInteractiveProposal::query()->where('token', $token)->lockForUpdate()->firstOrFail();

                if (! $proposal->canReceiveResponse()) {
                    throw new \DomainException('Proposta ja respondida ou indisponivel.');
                }

                $accepted = $data['action'] === 'accept';
                $proposal->update([
                    'status' => $accepted ? CrmInteractiveProposal::STATUS_ACCEPTED : CrmInteractiveProposal::STATUS_REJECTED,
                    'client_notes' => $data['client_notes'] ?? null,
                    'client_signature' => $data['client_signature'] ?? null,
                    'item_interactions' => $data['item_interactions'] ?? null,
                    'accepted_at' => $accepted ? now() : null,
                    'rejected_at' => $accepted ? null : now(),
                ]);

                if ($accepted && $proposal->quote_id) {
                    $quote = Quote::query()->where('tenant_id', $proposal->tenant_id)->find($proposal->quote_id);
                    if (! $quote) {
                        throw new \DomainException('Orcamento vinculado nao encontrado.');
                    }
                    $this->quoteService->publicApprove($quote);
                }

                $proposal->loadMissing('quote:id,customer_id');
                $this->createTrackingEvent(
                    tenantId: $proposal->tenant_id, trackableType: CrmInteractiveProposal::class,
                    trackableId: $proposal->id, eventType: $accepted ? 'proposal_accepted' : 'proposal_rejected',
                    customerId: $proposal->quote?->customer_id, dealId: $proposal->deal_id,
                );

                return $accepted ? 'Proposta aceita!' : 'Proposta recusada';
            });
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('CrmProposalTracking respondToProposal failed', ['token' => $token, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao processar resposta da proposta.', 500);
        }

        return ApiResponse::message($message);
    }

    // ── Tracking ──────────────────────────────────────────

    public function trackingEvents(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        return ApiResponse::paginated(
            CrmTrackingEvent::where('tenant_id', $this->tenantId($request))
                ->with(['customer:id,name', 'deal:id,title'])
                ->when($request->input('event_type'), fn ($q, $t) => $q->byType($t))
                ->when($request->input('deal_id'), fn ($q, $id) => $q->where('deal_id', $id))
                ->when($request->input('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
                ->orderByDesc('created_at')
                ->paginate(min((int) $request->input('per_page', 30), 100))
        );
    }

    public function trackingStats(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $since = $request->has('since') ? Carbon::parse($request->input('since')) : now()->subDays(30);

        $total = CrmTrackingEvent::where('tenant_id', $tenantId)->where('created_at', '>=', $since)->count();
        $byType = CrmTrackingEvent::where('tenant_id', $tenantId)->where('created_at', '>=', $since)
            ->selectRaw('event_type, COUNT(*) as count')->groupBy('event_type')->pluck('count', 'event_type')->toArray();

        return ApiResponse::data(['total_events' => $total, 'by_type' => $byType]);
    }

    public function trackingPixel(string $trackingId): mixed
    {
        $parts = explode('-', $trackingId);
        if (count($parts) >= 3) {
            $tenantId = $parts[0];
            $type = $parts[1];
            $entityId = $parts[2];

            try {
                $trackableType = $type === 'msg' ? CrmMessage::class : CrmInteractiveProposal::class;
                $customerId = null;
                $dealId = null;

                if ($trackableType === CrmMessage::class) {
                    $message = CrmMessage::query()->where('tenant_id', (int) $tenantId)->find($entityId);
                    $customerId = $message?->customer_id;
                    $dealId = $message?->deal_id;
                } else {
                    $proposal = CrmInteractiveProposal::query()->where('tenant_id', (int) $tenantId)->with('quote:id,customer_id')->find($entityId);
                    $customerId = $proposal?->quote?->customer_id;
                    $dealId = $proposal?->deal_id;
                }

                $this->createTrackingEvent(
                    tenantId: (int) $tenantId, trackableType: $trackableType,
                    trackableId: (int) $entityId, eventType: 'email_opened',
                    customerId: $customerId, dealId: $dealId,
                );
            } catch (\Throwable $e) {
                Log::warning('Tracking pixel error', ['error' => $e->getMessage()]);
            }
        }

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache');
    }
}
