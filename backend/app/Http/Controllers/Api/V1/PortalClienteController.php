<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FinancialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\AvailableSlotsRequest;
use App\Http\Requests\Portal\BatchCertificateDownloadRequest;
use App\Http\Requests\Portal\BiSelfServiceReportRequest;
use App\Http\Requests\Portal\BookSlotRequest;
use App\Http\Requests\Portal\OpenTicketByQrCodeRequest;
use App\Http\Requests\Portal\RegisterPushSubscriptionRequest;
use App\Http\Requests\Portal\SendChatMessageRequest;
use App\Http\Requests\Portal\StoreCustomerLocationRequest;
use App\Http\Requests\Portal\StoreKnowledgeBaseArticleRequest;
use App\Http\Requests\Portal\SubmitNpsRequest;
use App\Http\Requests\Portal\UpdateWhiteLabelConfigRequest;
use App\Models\Customer;
use App\Models\PushSubscription;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PortalClienteController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    private function ensureCustomerBelongsToTenant(int $customerId): void
    {
        $exists = Customer::where('tenant_id', $this->tenantId())
            ->where('id', $customerId)
            ->exists();

        abort_unless($exists, 404, 'Cliente não encontrado.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1. EXECUTIVE DASHBOARD (Dashboard Executivo)
    // ═══════════════════════════════════════════════════════════════════

    public function executiveDashboard(int $customerId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $this->ensureCustomerBelongsToTenant($customerId);
        $hasCertificatesTable = Schema::hasTable('calibration_certificates');

        $stats = [
            'total_os' => DB::table('work_orders')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->count(),
            'os_pending' => DB::table('work_orders')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->whereIn('status', self::executiveDashboardPendingStatuses())
                ->count(),
            'os_completed' => DB::table('work_orders')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->where('status', WorkOrder::STATUS_COMPLETED)->count(),
            'total_certificates' => $hasCertificatesTable
                ? DB::table('calibration_certificates')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->count()
                : 0,
            'certificates_valid' => $hasCertificatesTable
                ? DB::table('calibration_certificates')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->where('valid_until', '>', now())->count()
                : 0,
            'certificates_expiring' => $hasCertificatesTable
                ? DB::table('calibration_certificates')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->whereBetween('valid_until', [now(), now()->addDays(30)])->count()
                : 0,
            'open_invoices' => DB::table('accounts_receivable')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
                ->sum(DB::raw('amount - amount_paid')),
            'last_service_date' => DB::table('work_orders')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->max('completed_at'),
        ];

        $recentOs = DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'number', 'status', 'created_at', 'scheduled_at']);

        return ApiResponse::data(['stats' => $stats, 'recent_orders' => $recentOs]);
    }

    /**
     * @return list<string>
     */
    private static function executiveDashboardPendingStatuses(): array
    {
        return [
            WorkOrder::STATUS_PENDING,
            WorkOrder::STATUS_OPEN,
            WorkOrder::STATUS_AWAITING_DISPATCH,
            WorkOrder::STATUS_IN_PROGRESS,
            WorkOrder::STATUS_IN_DISPLACEMENT,
            WorkOrder::STATUS_DISPLACEMENT_PAUSED,
            WorkOrder::STATUS_AT_CLIENT,
            WorkOrder::STATUS_IN_SERVICE,
            WorkOrder::STATUS_SERVICE_PAUSED,
            WorkOrder::STATUS_WAITING_PARTS,
            WorkOrder::STATUS_WAITING_APPROVAL,
            WorkOrder::STATUS_AWAITING_RETURN,
            WorkOrder::STATUS_IN_RETURN,
            WorkOrder::STATUS_RETURN_PAUSED,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. BATCH CERTIFICATE DOWNLOAD
    // ═══════════════════════════════════════════════════════════════════

    public function batchCertificateDownload(BatchCertificateDownloadRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = DB::table('calibration_certificates')
            ->where('tenant_id', $this->tenantId())
            ->where('customer_id', $validated['customer_id']);

        if (! empty($validated['certificate_ids'])) {
            $query->whereIn('id', $validated['certificate_ids']);
        }
        if (! empty($validated['date_from'])) {
            $query->where('issued_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('issued_at', '<=', $validated['date_to']);
        }

        $certificates = $query->select('id', 'number', 'issued_at', 'valid_until', 'file_path')->get();

        return ApiResponse::data($certificates, 200, ['total' => $certificates->count(), 'message' => "Preparados {$certificates->count()} certificados para download"]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. QR CODE TICKET OPENING
    // ═══════════════════════════════════════════════════════════════════

    public function openTicketByQrCode(OpenTicketByQrCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('support_tickets')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'customer_id' => $validated['customer_id'],
                'source' => 'qr_code',
                'qr_data' => $validated['qr_data'],
                'description' => $validated['description'],
                'priority' => $validated['priority'] ?? 'medium',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['ticket_id' => $id], 201, ['message' => 'Chamado aberto com sucesso via QR Code']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('QR code ticket creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao abrir chamado.', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. ONE-CLICK QUOTE APPROVAL
    // ═══════════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════════
    // 5. REAL-TIME SUPPORT CHAT
    // ═══════════════════════════════════════════════════════════════════

    public function chatMessages(Request $request, int $ticketId): JsonResponse
    {
        $messages = DB::table('chat_messages')
            ->where('tenant_id', $this->tenantId())
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at')
            ->get();

        return ApiResponse::data($messages);
    }

    public function sendChatMessage(SendChatMessageRequest $request, int $ticketId): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('chat_messages')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'ticket_id' => $ticketId,
                'sender_id' => auth()->id(),
                'sender_type' => $validated['sender_type'],
                'message' => $validated['message'],
                'created_at' => now(),
            ]);

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Mensagem enviada']);
        } catch (\Exception $e) {
            Log::error('Chat message send failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao enviar mensagem.', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. FINANCIAL HISTORY & 2ND INVOICE COPY
    // ═══════════════════════════════════════════════════════════════════

    public function financialHistory(Request $request, int $customerId): JsonResponse
    {
        $this->ensureCustomerBelongsToTenant($customerId);

        $data = DB::table('accounts_receivable')
            ->where('tenant_id', $this->tenantId())
            ->where('customer_id', $customerId)
            ->orderByDesc('due_date')
            ->paginate(20);

        return ApiResponse::paginated($data);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 7. ONLINE SELF-SCHEDULING
    // ═══════════════════════════════════════════════════════════════════

    public function availableSlots(AvailableSlotsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $date = Carbon::parse($validated['date']);
        $slots = [];
        $hours = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];

        $bookedSlots = DB::table('work_orders')
            ->where('tenant_id', $this->tenantId())
            ->whereDate('scheduled_at', $date)
            ->pluck('scheduled_at')
            ->map(fn ($s) => Carbon::parse($s)->format('H:i'))
            ->toArray();

        foreach ($hours as $hour) {
            $slots[] = [
                'time' => $hour,
                'available' => ! in_array($hour, $bookedSlots),
            ];
        }

        return ApiResponse::data(['date' => $date->format('Y-m-d'), 'slots' => $slots]);
    }

    public function bookSlot(BookSlotRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $scheduledAt = Carbon::parse($validated['date'].' '.$validated['time']);

            $id = DB::table('scheduled_appointments')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'customer_id' => $validated['customer_id'],
                'scheduled_at' => $scheduledAt,
                'service_type' => $validated['service_type'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'confirmed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Agendamento confirmado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Slot booking failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao agendar.', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 8. BROWSER PUSH NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════════

    public function registerPushSubscription(RegisterPushSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            PushSubscription::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'endpoint' => $validated['endpoint'],
                ],
                [
                    'tenant_id' => $this->tenantId(),
                    'p256dh_key' => $validated['keys']['p256dh'],
                    'auth_key' => $validated['keys']['auth'],
                    'user_agent' => $request->userAgent(),
                ]
            );

            return ApiResponse::message('Inscrição push registrada com sucesso.');
        } catch (\Exception $e) {
            Log::error('Push subscription registration failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar inscrição push.', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 9. KNOWLEDGE BASE (FAQ)
    // ═══════════════════════════════════════════════════════════════════

    public function knowledgeBase(Request $request): JsonResponse
    {
        $articles = DB::table('knowledge_base_articles')
            ->where('tenant_id', $this->tenantId())
            ->where('published', true)
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->input('search'), function ($q, $s) {
                $safe = SearchSanitizer::contains($s);
                $q->where(function ($sub) use ($safe) {
                    $sub->where('title', 'like', $safe)
                        ->orWhere('content', 'like', $safe);
                });
            })
            ->orderBy('sort_order')
            ->paginate(20);

        return ApiResponse::paginated($articles);
    }

    public function storeArticle(StoreKnowledgeBaseArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('knowledge_base_articles')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'title' => $validated['title'],
                'content' => $validated['content'],
                'category' => $validated['category'],
                'published' => $validated['published'] ?? false,
                'sort_order' => 0,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Artigo criado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Knowledge base article creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar artigo.', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 10. MULTI-LOCATION MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════

    public function customerLocations(int $customerId): JsonResponse
    {
        $this->ensureCustomerBelongsToTenant($customerId);

        $locations = DB::table('customer_locations')
            ->where('tenant_id', $this->tenantId())
            ->where('customer_id', $customerId)
            ->orderBy('name')
            ->get();

        return ApiResponse::data($locations);
    }

    public function storeLocation(StoreCustomerLocationRequest $request, int $customerId): JsonResponse
    {
        $this->ensureCustomerBelongsToTenant($customerId);

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $validated['customer_id'] = $customerId;
            $validated['tenant_id'] = $this->tenantId();
            $validated['created_at'] = now();
            $validated['updated_at'] = now();

            $id = DB::table('customer_locations')->insertGetId($validated);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Localidade cadastrada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer location creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao cadastrar localidade.', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 11. PUBLIC API
    // ═══════════════════════════════════════════════════════════════════

    public function publicApiOverview(): JsonResponse
    {
        return ApiResponse::data([
            'version' => 'v1',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/v1/portal/dashboard/{customerId}', 'description' => 'Dashboard executivo'],
                ['method' => 'GET', 'path' => '/api/v1/portal/certificates/{customerId}', 'description' => 'Certificados'],
                ['method' => 'POST', 'path' => '/api/v1/portal/tickets', 'description' => 'Abrir chamado'],
                ['method' => 'GET', 'path' => '/api/v1/portal/financial/{customerId}', 'description' => 'Histórico financeiro'],
                ['method' => 'GET', 'path' => '/api/v1/portal/schedule/slots', 'description' => 'Horários disponíveis'],
                ['method' => 'POST', 'path' => '/api/v1/portal/schedule/book', 'description' => 'Agendar atendimento'],
            ],
            'rate_limit' => '100 requests/minute',
            'auth' => 'Bearer token (API Key)',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 12. WHITE LABEL CONFIG
    // ═══════════════════════════════════════════════════════════════════

    public function whiteLabelConfig(): JsonResponse
    {
        $config = DB::table('portal_white_label')
            ->where('tenant_id', $this->tenantId())
            ->first();

        return ApiResponse::data($config ?? ['theme' => 'default', 'logo' => null, 'colors' => []]);
    }

    public function updateWhiteLabelConfig(UpdateWhiteLabelConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('portal_white_label')->updateOrInsert(
                ['tenant_id' => $this->tenantId()],
                array_merge($validated, ['updated_at' => now()])
            );

            return ApiResponse::message('Configuração atualizada.');
        } catch (\Exception $e) {
            Log::error('White label config update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar configuração.', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 13. NPS SURVEYS
    // ═══════════════════════════════════════════════════════════════════

    public function npsSurveys(Request $request): JsonResponse
    {
        $data = DB::table('nps_surveys')
            ->where('tenant_id', $this->tenantId())
            ->when($request->input('customer_id'), fn ($q, $c) => $q->where('customer_id', $c))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($data);
    }

    public function submitNps(SubmitNpsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('nps_surveys')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'customer_id' => $validated['customer_id'],
                'work_order_id' => $validated['work_order_id'] ?? null,
                'score' => $validated['score'],
                'category' => match (true) {
                    $validated['score'] >= 9 => 'promoter',
                    $validated['score'] >= 7 => 'neutral',
                    default => 'detractor',
                },
                'comment' => $validated['comment'] ?? null,
                'created_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Avaliação registrada. Obrigado!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('NPS submission failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar avaliação.', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 14. EQUIPMENT VISUAL MAP
    // ═══════════════════════════════════════════════════════════════════

    public function equipmentMap(int $customerId): JsonResponse
    {
        $this->ensureCustomerBelongsToTenant($customerId);
        $tenantId = $this->tenantId();

        $equipment = DB::table('customer_equipment')
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->select('id', 'name', 'type', 'serial_number', 'location', 'last_calibration_at', 'next_calibration_at', 'status')
            ->orderBy('location')
            ->orderBy('name')
            ->get()
            ->groupBy('location');

        return ApiResponse::data($equipment);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 15. BI SELF-SERVICE REPORTS
    // ═══════════════════════════════════════════════════════════════════

    public function biSelfServiceReport(BiSelfServiceReportRequest $request, int $customerId): JsonResponse
    {
        $this->ensureCustomerBelongsToTenant($customerId);

        $validated = $request->validated();
        $tenantId = $this->tenantId();

        $data = match ($validated['report_type']) {
            'calibration_history' => DB::table('calibration_certificates')
                ->where('tenant_id', $tenantId)->where('customer_id', $customerId)
                ->when($validated['date_from'] ?? null, fn ($q, $d) => $q->where('issued_at', '>=', $d))
                ->select(DB::raw('MONTH(issued_at) as month'), DB::raw('COUNT(*) as total'))
                ->groupByRaw('MONTH(issued_at)')
                ->get(),
            'cost_analysis' => DB::table('work_orders')
                ->where('tenant_id', $tenantId)->where('customer_id', $customerId)
                ->select(DB::raw('MONTH(created_at) as month'), DB::raw('SUM(total) as total_cost'), DB::raw('COUNT(*) as os_count'))
                ->groupByRaw('MONTH(created_at)')
                ->get(),
            'compliance_status' => [
                'total_equipment' => DB::table('customer_equipment')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->count(),
                'calibrated' => DB::table('customer_equipment')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->where('next_calibration_at', '>', now())->count(),
                'overdue' => DB::table('customer_equipment')->where('tenant_id', $tenantId)->where('customer_id', $customerId)->where('next_calibration_at', '<', now())->count(),
            ],
            'equipment_lifecycle' => DB::table('customer_equipment')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->select('id', 'name', 'purchased_at', 'last_calibration_at', 'next_calibration_at', 'status')
                ->get(),
            default => [],
        };

        return ApiResponse::data(['data' => $data, 'report_type' => $validated['report_type']]);
    }
}
