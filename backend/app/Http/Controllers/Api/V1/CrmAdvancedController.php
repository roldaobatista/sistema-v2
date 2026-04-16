<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CreatePipelineRequest;
use App\Http\Requests\Crm\MergeLeadsRequest;
use App\Http\Requests\Crm\PublicSignQuoteRequest;
use App\Http\Requests\Crm\SendQuoteForSignatureRequest;
use App\Http\Requests\Crm\StoreFunnelAutomationRequest;
use App\Http\Requests\Crm\UpdateFunnelAutomationRequest;
use App\Models\CrmDeal;
use App\Models\CrmFunnelAutomation;
use App\Models\CrmLeadScore;
use App\Models\CrmLeadScoringRule;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Quote;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrmAdvancedController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ── Funnel Automations ───────────────────────────────

    public function funnelAutomations(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $automations = CrmFunnelAutomation::with(['pipeline:id,name', 'stage:id,name'])
            ->orderBy('sort_order')
            ->paginate(15);

        return ApiResponse::paginated($automations);
    }

    public function storeFunnelAutomation(StoreFunnelAutomationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $automation = CrmFunnelAutomation::create([
            ...$data,
            'tenant_id' => $this->tenantId($request),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::data($automation->load(['pipeline:id,name', 'stage:id,name']), 201);
    }

    public function updateFunnelAutomation(UpdateFunnelAutomationRequest $request, int $id): JsonResponse
    {
        $automation = CrmFunnelAutomation::findOrFail($id);

        $automation->update($request->validated());

        return ApiResponse::data($automation->load(['pipeline:id,name', 'stage:id,name']));
    }

    public function deleteFunnelAutomation(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.delete'), 403);

        $automation = CrmFunnelAutomation::findOrFail($id);

        $automation->delete();

        return ApiResponse::message('Automação removida com sucesso.');
    }

    // ── Lead Scoring Recalculation ───────────────────────

    public function recalculateLeadScores(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.scoring.manage'), 403);

        $tenantId = $this->tenantId($request);
        $rules = CrmLeadScoringRule::where('tenant_id', $tenantId)->get();

        if ($rules->isEmpty()) {
            return ApiResponse::message('Nenhuma regra de scoring configurada.', 422);
        }

        $ruleFields = $rules->pluck('field')->unique()->filter()->all();
        $selectFields = array_unique(array_merge(['id', 'tenant_id'], $ruleFields));

        $calculated = 0;

        Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select($selectFields)
            ->chunkById(500, function ($customers) use ($rules, $tenantId, &$calculated) {
                foreach ($customers as $customer) {
                    $totalScore = 0;
                    $breakdown = [];

                    foreach ($rules as $rule) {
                        $fieldValue = $customer->{$rule->field} ?? null;
                        $matches = $this->evaluateScoringRule($rule, $fieldValue);

                        if ($matches) {
                            $totalScore += $rule->points;
                            $breakdown[] = [
                                'rule_id' => $rule->id,
                                'rule_name' => $rule->name,
                                'points' => $rule->points,
                                'field' => $rule->field,
                            ];
                        }
                    }

                    $grade = CrmLeadScore::calculateGrade($totalScore);
                    CrmLeadScore::updateOrCreate(
                        ['tenant_id' => $tenantId, 'customer_id' => $customer->id],
                        ['total_score' => $totalScore, 'score_breakdown' => $breakdown, 'grade' => $grade, 'calculated_at' => now()]
                    );

                    $customer->update(['lead_score' => $totalScore, 'lead_grade' => $grade]);
                    $calculated++;
                }
            });

        return ApiResponse::data([
            'calculated' => $calculated,
            'message' => "Scores recalculados para {$calculated} clientes.",
        ]);
    }

    private function evaluateScoringRule(CrmLeadScoringRule $rule, mixed $value): bool
    {
        return match ($rule->operator) {
            'equals' => $value == $rule->value,
            'not_equals' => $value != $rule->value,
            'greater_than' => $value > $rule->value,
            'less_than' => $value < $rule->value,
            'contains' => is_string($value) && str_contains(strtolower($value), strtolower($rule->value)),
            'not_contains' => is_string($value) && ! str_contains(strtolower($value), strtolower($rule->value)),
            'is_empty' => empty($value),
            'is_not_empty' => ! empty($value),
            default => false,
        };
    }

    // ── Quote Signature ──────────────────────────────────

    public function sendQuoteForSignature(SendQuoteForSignatureRequest $request, Quote $quote): JsonResponse
    {
        abort_if((int) $quote->tenant_id !== $this->tenantId($request), 404);

        $data = $request->validated();

        $token = Str::random(64);

        $quote->update([
            'signature_token' => $token,
            'signature_requested_at' => now(),
            'signer_name' => $data['signer_name'],
            'signer_email' => $data['signer_email'],
        ]);

        return ApiResponse::data([
            'message' => 'Orçamento enviado para assinatura.',
            'token' => $token,
        ]);
    }

    public function signQuote(PublicSignQuoteRequest $request, string $token): JsonResponse
    {
        // Rota pública (assinatura via token) — não exige autenticação
        $quote = Quote::where('signature_token', $token)
            ->whereNull('signed_at')
            ->firstOrFail();

        $data = $request->validated();

        $quote->update([
            'signed_at' => now(),
            'signed_by_name' => $data['signer_name'],
            'signed_by_ip' => $request->ip(),
            'status' => 'approved',
        ]);

        return ApiResponse::data([
            'message' => 'Orçamento assinado com sucesso.',
            'signed_at' => $quote->signed_at,
        ]);
    }

    // ── Sales Forecast ───────────────────────────────────

    public function salesForecast(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.forecast.view'), 403);

        $tenantId = $this->tenantId($request);
        $months = min((int) $request->input('months', 3), 12);

        $forecast = [];
        for ($i = 0; $i < $months; $i++) {
            $start = now()->addMonths($i)->startOfMonth();
            $end = now()->addMonths($i)->endOfMonth();

            $query = CrmDeal::where('tenant_id', $tenantId)
                ->whereIn('status', ['open', 'negotiation', 'proposal'])
                ->where(function ($q) use ($start, $end, $i) {
                    $q->whereBetween('expected_close_date', [$start, $end]);
                    if ($i === 0) {
                        $q->orWhereNull('expected_close_date');
                    }
                });

            $pipelineValue = (float) $query->sum('value');
            $dealCount = $query->count();

            // Weighted value precisa de cálculo por registro
            $weightedValue = (float) (clone $query)
                ->selectRaw('SUM(value * COALESCE(probability, 50) / 100) as weighted')
                ->value('weighted');

            $forecast[] = [
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'pipeline_value' => round($pipelineValue, 2),
                'weighted_value' => round($weightedValue, 2),
                'deal_count' => $dealCount,
            ];
        }

        return ApiResponse::data(['forecast' => $forecast]);
    }

    // ── Duplicate Leads ──────────────────────────────────

    public function findDuplicateLeads(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);

        $duplicatesByEmail = Customer::where('tenant_id', $tenantId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->selectRaw('email, COUNT(*) as duplicate_count')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->limit(50)
            ->get()
            ->map(function (Customer $row) use ($tenantId): array {
                $email = (string) $row->getAttribute('email');
                $ids = Customer::where('tenant_id', $tenantId)
                    ->where('email', $email)
                    ->pluck('id')
                    ->all();

                return [
                    'field' => 'email',
                    'value' => $email,
                    'count' => (int) $row->getAttribute('duplicate_count'),
                    'customer_ids' => $ids,
                ];
            });

        $duplicatesByDoc = Customer::where('tenant_id', $tenantId)
            ->whereNotNull('document')
            ->where('document', '!=', '')
            ->selectRaw('document, COUNT(*) as duplicate_count')
            ->groupBy('document')
            ->havingRaw('COUNT(*) > 1')
            ->limit(50)
            ->get()
            ->map(function (Customer $row) use ($tenantId): array {
                $document = (string) $row->getAttribute('document');
                $ids = Customer::where('tenant_id', $tenantId)
                    ->where('document', $document)
                    ->pluck('id')
                    ->all();

                return [
                    'field' => 'document',
                    'value' => $document,
                    'count' => (int) $row->getAttribute('duplicate_count'),
                    'customer_ids' => $ids,
                ];
            });

        $duplicates = $duplicatesByEmail->merge($duplicatesByDoc)->values();

        return ApiResponse::data([
            'duplicates' => $duplicates,
            'total_groups' => $duplicates->count(),
        ]);
    }

    // ── Merge Leads ──────────────────────────────────────

    public function mergeLeads(MergeLeadsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $this->tenantId($request);

        $primaryId = (int) $data['primary_id'];
        $primary = Customer::where('tenant_id', $tenantId)->findOrFail($primaryId);

        $mergeIds = $data['merge_ids'] ?? [$data['secondary_id'] ?? null];
        if (! is_array($mergeIds)) {
            $mergeIds = [$mergeIds];
        }
        $mergeIds = array_values(array_filter(
            array_map(static fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null, $mergeIds)
        ));

        $secondaries = Customer::where('tenant_id', $tenantId)
            ->whereIn('id', $mergeIds)
            ->get();

        abort_if($secondaries->isEmpty(), 422, 'Nenhum cliente válido para mesclar.');

        DB::transaction(function () use ($primary, $secondaries) {
            foreach ($secondaries as $secondary) {
                // Transfer deals
                CrmDeal::where('customer_id', $secondary->id)->update(['customer_id' => $primary->id]);

                // Transfer work orders
                DB::table('work_orders')->where('customer_id', $secondary->id)->update(['customer_id' => $primary->id]);

                // Transfer accounts receivable
                DB::table('accounts_receivable')->where('customer_id', $secondary->id)->update(['customer_id' => $primary->id]);

                // Transfer quotes
                DB::table('quotes')->where('customer_id', $secondary->id)->update(['customer_id' => $primary->id]);

                // Fill empty fields from secondary
                $fillableFields = ['phone', 'email', 'address', 'city', 'state', 'zip_code', 'document'];
                foreach ($fillableFields as $field) {
                    if (empty($primary->getAttribute($field)) && ! empty($secondary->getAttribute($field))) {
                        $primary->setAttribute($field, $secondary->getAttribute($field));
                    }
                }

                // Soft-delete secondary
                $secondary->update(['is_active' => false]);
                $secondary->delete();
            }

            $primary->save();
        });

        return ApiResponse::data([
            'message' => 'Clientes mesclados com sucesso.',
            'primary' => $primary->fresh(),
        ]);
    }

    // ── Multi-Product Pipelines ──────────────────────────

    public function multiProductPipelines(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.pipeline.view'), 403);

        $pipelines = CrmPipeline::with(['stages' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount('deals')
            ->orderBy('sort_order')
            ->paginate(15);

        return ApiResponse::paginated($pipelines);
    }

    public function createPipeline(CreatePipelineRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $this->tenantId($request);

        $pipeline = DB::transaction(function () use ($data, $tenantId) {
            if (! empty($data['is_default'])) {
                CrmPipeline::where('tenant_id', $tenantId)->update(['is_default' => false]);
            }

            $pipeline = CrmPipeline::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'color' => $data['color'] ?? null,
                'product_category' => $data['product_category'] ?? null,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => true,
                'sort_order' => CrmPipeline::where('tenant_id', $tenantId)->max('sort_order') + 1,
            ]);

            foreach ($data['stages'] as $index => $stageData) {
                CrmPipelineStage::create([
                    'pipeline_id' => $pipeline->id,
                    'name' => $stageData['name'],
                    'color' => $stageData['color'] ?? null,
                    'probability' => $stageData['probability'] ?? 0,
                    'sort_order' => $index,
                ]);
            }

            return $pipeline;
        });

        return ApiResponse::data($pipeline->load('stages'), 201);
    }
}
