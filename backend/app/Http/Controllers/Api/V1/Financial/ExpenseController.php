<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\BatchUpdateBudgetLimitsRequest;
use App\Http\Requests\Financial\BatchUpdateExpenseStatusRequest;
use App\Http\Requests\Financial\StoreExpenseCategoryRequest;
use App\Http\Requests\Financial\StoreExpenseRequest;
use App\Http\Requests\Financial\UpdateExpenseCategoryRequest;
use App\Http\Requests\Financial\UpdateExpenseRequest;
use App\Http\Requests\Financial\UpdateExpenseStatusRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseStatusHistory;
use App\Models\Role;
use App\Models\TechnicianCashFund;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\ExpenseReceiptStorage;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExpenseController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        try {
            $tenantId = $this->tenantId();
            $query = Expense::with(['category:id,name,color', 'creator:id,name', 'approver:id,name', 'workOrder:id,number,os_number', 'chartOfAccount:id,code,name,type', 'reviewer:id,name'])
                ->where('tenant_id', $tenantId);

            if ($request->get('my')) {
                $query->where('created_by', $request->user()->id);
            }
            if ($search = $request->get('search')) {
                $search = SearchSanitizer::escapeLike($search);
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhereHas('workOrder', function ($wo) use ($search) {
                            $wo->where('number', 'like', "%{$search}%")
                                ->orWhere('os_number', 'like', "%{$search}%");
                        });
                });
            }
            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($catId = $request->get('expense_category_id')) {
                $query->where('expense_category_id', $catId);
            }
            if ($userId = $request->get('created_by')) {
                $query->where('created_by', $userId);
            }
            if ($woId = $request->get('work_order_id')) {
                $query->where('work_order_id', $woId);
            }
            if ($from = $request->get('date_from')) {
                $query->where('expense_date', '>=', $from);
            }
            if ($to = $request->get('date_to')) {
                $query->where('expense_date', '<=', $to);
            }

            $records = $query->orderByDesc('expense_date')
                ->paginate(min((int) $request->get('per_page', 50), 100));

            return ApiResponse::paginated($records, resourceClass: ExpenseResource::class);
        } catch (\Exception $e) {
            Log::error('Expense index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar despesas', 500);
        }
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $this->authorize('create', Expense::class);

        try {
            $tenantId = $this->tenantId();
            $validated = $request->validated();

            $receiptPath = ExpenseReceiptStorage::store($request->file('receipt'), $tenantId);

            try {
                $expense = DB::transaction(function () use ($validated, $tenantId, $request, $receiptPath) {
                    $data = $this->applyCategoryDefaults($validated, $tenantId);
                    if ($receiptPath) {
                        $data['receipt_path'] = $receiptPath;
                    }
                    unset($data['receipt'], $data['status']);

                    $expense = new Expense([
                        ...$data,
                        'tenant_id' => $tenantId,
                        'created_by' => $request->user()->id,
                    ]);
                    $expense->forceFill(['status' => ExpenseStatus::PENDING]);

                    $expense->save();

                    ExpenseStatusHistory::create([
                        'expense_id' => $expense->id,
                        'changed_by' => $request->user()->id,
                        'from_status' => null,
                        'to_status' => ExpenseStatus::PENDING->value,
                    ]);

                    return $expense;
                });
            } catch (\Throwable $e) {
                ExpenseReceiptStorage::deleteQuietly($receiptPath, [
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()?->id,
                    'action' => 'expense-store',
                ]);

                throw $e;
            }

            // Auditar se despesa vinculada a OS
            if ($expense->work_order_id) {
                $wo = WorkOrder::find($expense->work_order_id);
                if ($wo) {
                    AuditLog::log('expense_added', 'Despesa R$ '.number_format((float) $expense->amount, 2, ',', '.')." adicionada à OS {$wo->business_number}", $wo, [], [
                        'expense_id' => $expense->id,
                        'amount' => $expense->amount,
                        'description' => $expense->description,
                    ]);
                }
            }

            $duplicateCount = Expense::where('tenant_id', $tenantId)
                ->where('description', $validated['description'])
                ->where('amount', $validated['amount'])
                ->where('expense_date', $validated['expense_date'])
                ->where('id', '!=', $expense->id)
                ->count();

            $budgetWarning = $this->checkBudgetLimit($expense, $tenantId);

            $loaded = $expense->load(['category:id,name,color', 'creator:id,name', 'workOrder:id,number,os_number', 'chartOfAccount:id,code,name,type']);
            $extra = [];
            if ($duplicateCount > 0) {
                $extra['_warning'] = "Possivel duplicidade: {$duplicateCount} despesa(s) com mesma descricao, valor e data.";
            }
            if ($budgetWarning) {
                $extra['_budget_warning'] = $budgetWarning;
            }

            return ApiResponse::data(new ExpenseResource($loaded), 201, $extra);
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Expense store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao criar despesa', 500);
        }
    }

    public function show(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        try {
            if ((int) $expense->tenant_id !== $this->tenantId()) {
                return ApiResponse::message('Despesa não encontrada', 404);
            }

            return ApiResponse::data(new ExpenseResource($expense->load([
                'category:id,name,color',
                'creator:id,name',
                'approver:id,name',
                'reviewer:id,name',
                'workOrder:id,number,os_number',
                'chartOfAccount:id,code,name,type',
            ])));
        } catch (\Exception $e) {
            Log::error('Expense show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar despesa', 500);
        }
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);

        try {
            $tenantId = $this->tenantId();

            if ($expense->tenant_id !== $tenantId) {
                return ApiResponse::message('Despesa não encontrada', 404);
            }

            $validated = $request->validated();
            $data = $this->applyCategoryDefaults($validated, $tenantId);
            unset($data['receipt'], $data['status']);

            if ($request->hasFile('receipt')) {
                $data['receipt_path'] = ExpenseReceiptStorage::store($request->file('receipt'), $tenantId);
            }

            $oldReceiptPath = null;

            try {
                DB::transaction(function () use ($expense, $data, &$oldReceiptPath) {
                    $locked = Expense::lockForUpdate()->findOrFail($expense->id);
                    if (in_array($locked->status, [ExpenseStatus::APPROVED, ExpenseStatus::REIMBURSED], true)) {
                        abort(422, 'Não é possível editar despesa já aprovada ou reembolsada');
                    }
                    $oldReceiptPath = $locked->receipt_path;
                    $locked->update($data);
                });
            } catch (HttpException $e) {
                if (isset($data['receipt_path'])) {
                    ExpenseReceiptStorage::deleteQuietly($data['receipt_path'], [
                        'tenant_id' => $tenantId,
                        'user_id' => $request->user()?->id,
                        'expense_id' => $expense->id,
                        'action' => 'expense-update',
                    ]);
                }

                return ApiResponse::message($e->getMessage(), $e->getStatusCode());
            } catch (\Throwable $e) {
                if (isset($data['receipt_path'])) {
                    ExpenseReceiptStorage::deleteQuietly($data['receipt_path'], [
                        'tenant_id' => $tenantId,
                        'user_id' => $request->user()?->id,
                        'expense_id' => $expense->id,
                        'action' => 'expense-update',
                    ]);
                }

                throw $e;
            }

            if (isset($data['receipt_path']) && $oldReceiptPath && $oldReceiptPath !== $data['receipt_path']) {
                ExpenseReceiptStorage::deleteQuietly($oldReceiptPath, [
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()?->id,
                    'expense_id' => $expense->id,
                    'action' => 'expense-replace',
                ]);
            }

            $expense->refresh();
            $budgetWarning = $this->checkBudgetLimit($expense, $tenantId);
            $loaded = $expense->load(['category:id,name,color', 'workOrder:id,number,os_number', 'chartOfAccount:id,code,name,type']);
            $extra = $budgetWarning ? ['_budget_warning' => $budgetWarning] : [];

            return ApiResponse::data(new ExpenseResource($loaded), 200, $extra);
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Expense update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao atualizar despesa', 500);
        }
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        try {
            if ((int) $expense->tenant_id !== $this->tenantId()) {
                return ApiResponse::message('Despesa não encontrada', 404);
            }

            $receiptPath = null;

            try {
                DB::transaction(function () use ($expense, &$receiptPath) {
                    $locked = Expense::lockForUpdate()->findOrFail($expense->id);
                    if (in_array($locked->status, [ExpenseStatus::APPROVED, ExpenseStatus::REIMBURSED], true)) {
                        abort(409, 'Não é possível excluir despesa já aprovada ou reembolsada');
                    }
                    $receiptPath = $locked->receipt_path;
                    $locked->delete();
                });
            } catch (HttpException $e) {
                return ApiResponse::message($e->getMessage(), $e->getStatusCode());
            }

            ExpenseReceiptStorage::deleteQuietly($receiptPath, [
                'tenant_id' => $this->tenantId(),
                'user_id' => $request->user()?->id,
                'expense_id' => $expense->id,
                'action' => 'expense-destroy',
            ]);

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Expense destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao excluir despesa', 500);
        }
    }

    public function updateStatus(UpdateExpenseStatusRequest $request, Expense $expense): JsonResponse
    {
        $this->authorize('approve', $expense);

        try {
            if ((int) $expense->tenant_id !== $this->tenantId()) {
                return ApiResponse::message('Despesa não encontrada', 404);
            }

            $validated = $request->validated();

            $newStatus = $validated['status'];
            $rejectionReason = trim((string) ($validated['rejection_reason'] ?? ''));

            $allowed = [
                ExpenseStatus::PENDING->value => [ExpenseStatus::REVIEWED->value, ExpenseStatus::APPROVED->value, ExpenseStatus::REJECTED->value],
                ExpenseStatus::REVIEWED->value => [ExpenseStatus::APPROVED->value, ExpenseStatus::REJECTED->value],
                ExpenseStatus::APPROVED->value => [ExpenseStatus::REIMBURSED->value],
                ExpenseStatus::REJECTED->value => [ExpenseStatus::PENDING->value],
            ];
            $currentStatusValue = $expense->status instanceof ExpenseStatus ? $expense->status->value : $expense->status;
            if (! in_array($newStatus, $allowed[$currentStatusValue] ?? [], true)) {
                return ApiResponse::message("Não é possivel mudar de '{$currentStatusValue}' para '{$newStatus}'", 422);
            }

            if (in_array($newStatus, [ExpenseStatus::APPROVED->value, ExpenseStatus::REJECTED->value], true) && $expense->created_by === $request->user()->id) {
                if (! $request->user()->hasRole(Role::SUPER_ADMIN)) {
                    return ApiResponse::message('Não é permitido aprovar/rejeitar sua propria despesa', 403);
                }
            }

            if ($newStatus === ExpenseStatus::REJECTED->value && $rejectionReason === '') {
                return ApiResponse::message('Informe o motivo da rejeicao', 422, [
                    'errors' => [
                        'rejection_reason' => ['O motivo da rejeicao e obrigatório.'],
                    ],
                ]);
            }

            $data = [
                'status' => $newStatus,
                'rejection_reason' => $newStatus === ExpenseStatus::REJECTED->value
                    ? $rejectionReason
                    : null,
            ];
            if ($newStatus === ExpenseStatus::REVIEWED->value) {
                $data['reviewed_by'] = $request->user()->id;
                $data['reviewed_at'] = now();
            } elseif ($newStatus === ExpenseStatus::APPROVED->value) {
                $data['approved_by'] = $request->user()->id;
            } elseif ($newStatus === ExpenseStatus::REJECTED->value) {
                $data['approved_by'] = null;
                $data['reviewed_by'] = null;
                $data['reviewed_at'] = null;
            } elseif ($newStatus === ExpenseStatus::PENDING->value) {
                $data['reviewed_by'] = null;
                $data['reviewed_at'] = null;
            }

            DB::transaction(function () use ($expense, $data, $newStatus, $rejectionReason, $request, $allowed) {
                $locked = Expense::lockForUpdate()->find($expense->id);
                if (! $locked) {
                    abort(404, 'Despesa não encontrada.');
                }
                $lockedStatusValue = $locked->status instanceof ExpenseStatus ? $locked->status->value : $locked->status;
                if (! in_array($newStatus, $allowed[$lockedStatusValue] ?? [], true)) {
                    abort(422, "Não é possivel mudar de '{$lockedStatusValue}' para '{$newStatus}'");
                }

                $locked->forceFill($data)->save();

                ExpenseStatusHistory::create([
                    'expense_id' => $locked->id,
                    'changed_by' => $request->user()->id,
                    'from_status' => $lockedStatusValue,
                    'to_status' => $newStatus,
                    'reason' => $newStatus === ExpenseStatus::REJECTED->value ? $rejectionReason : null,
                ]);

                if ($newStatus === ExpenseStatus::APPROVED->value && $locked->affects_technician_cash && $locked->created_by) {
                    $fund = TechnicianCashFund::getOrCreate($locked->created_by, $this->tenantId());
                    $expensePaymentMethod = $locked->payment_method === 'corporate_card' ? 'corporate_card' : 'cash';
                    $fund->addDebit(
                        (string) $locked->amount,
                        "Despesa #{$locked->id}: {$locked->description}",
                        $locked->id,
                        $request->user()->id,
                        $locked->work_order_id,
                        allowNegative: true,
                        paymentMethod: $expensePaymentMethod,
                    );
                }

                if ($newStatus === ExpenseStatus::REIMBURSED->value && $locked->affects_technician_cash && $locked->created_by) {
                    $fund = TechnicianCashFund::getOrCreate($locked->created_by, $this->tenantId());
                    $expensePaymentMethod = $locked->payment_method === 'corporate_card' ? 'corporate_card' : 'cash';
                    $fund->addCredit(
                        (string) $locked->amount,
                        "Reembolso da despesa #{$locked->id}: {$locked->description}",
                        $request->user()->id,
                        $locked->work_order_id,
                        paymentMethod: $expensePaymentMethod,
                    );
                }
            });

            return ApiResponse::data($expense->fresh()->load(['category:id,name,color', 'approver:id,name', 'chartOfAccount:id,code,name,type']));
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Expense updateStatus failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao atualizar status', 500);
        }
    }

    public function categories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        try {
            return ApiResponse::data(
                ExpenseCategory::where('tenant_id', $this->tenantId())
                    ->where('active', true)
                    ->withCount(['expenses' => fn ($q) => $q->withoutTrashed()])
                    ->orderBy('name')
                    ->get()
            );
        } catch (\Exception $e) {
            Log::error('Expense categories failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar categorias', 500);
        }
    }

    private function applyCategoryDefaults(array $data, int $tenantId): array
    {
        if (empty($data['expense_category_id'])) {
            return $data;
        }

        $category = ExpenseCategory::query()
            ->where('tenant_id', $tenantId)
            ->find($data['expense_category_id']);

        if (! $category) {
            return $data;
        }

        if (! array_key_exists('affects_net_value', $data) || $data['affects_net_value'] === null) {
            $data['affects_net_value'] = $category->default_affects_net_value ?? false;
        }

        if (! array_key_exists('affects_technician_cash', $data) || $data['affects_technician_cash'] === null) {
            $data['affects_technician_cash'] = $category->default_affects_technician_cash ?? false;
        }

        return $data;
    }

    /**
     * Atualizar limites orçamentários em lote.
     */
    public function batchUpdateLimits(BatchUpdateBudgetLimitsRequest $request): JsonResponse
    {
        $this->authorize('update', Expense::class);

        try {
            $tenantId = $this->tenantId();
            $updated = 0;

            DB::transaction(function () use ($request, $tenantId, &$updated) {
                foreach ($request->input('limits') as $item) {
                    $affected = ExpenseCategory::where('id', $item['id'])
                        ->where('tenant_id', $tenantId)
                        ->update(['budget_limit' => $item['budget_limit']]);
                    $updated += $affected;
                }
            });

            return ApiResponse::message("$updated limites atualizados com sucesso");
        } catch (\Exception $e) {
            Log::error('Batch update limits failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar limites', 500);
        }
    }

    public function storeCategory(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Expense::class);

        try {
            $tenantId = $this->tenantId();

            $validated = $request->validated();

            $category = DB::transaction(fn () => ExpenseCategory::create([
                ...$validated,
                'tenant_id' => $tenantId,
            ]));

            return ApiResponse::data($category, 201);
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Expense storeCategory failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao criar categoria', 500);
        }
    }

    public function updateCategory(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): JsonResponse
    {
        $this->authorize('update', Expense::class);

        try {
            $tenantId = $this->tenantId();

            if ((int) $category->tenant_id !== $tenantId) {
                return ApiResponse::message('Categoria não encontrada', 404);
            }

            $validated = $request->validated();

            DB::transaction(fn () => $category->update($validated));

            return ApiResponse::data($category->fresh());
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Expense updateCategory failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao atualizar categoria', 500);
        }
    }

    public function destroyCategory(Request $request, ExpenseCategory $category): JsonResponse
    {
        $this->authorize('delete', Expense::class);

        try {
            if ($category->tenant_id !== $this->tenantId()) {
                return ApiResponse::message('Categoria não encontrada', 404);
            }

            if ($category->expenses()->withoutTrashed()->exists()) {
                return ApiResponse::message('Categoria possui despesas vinculadas. Remova ou reclassifique antes de excluir.', 422);
            }

            DB::transaction(fn () => $category->delete());

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Expense destroyCategory failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao excluir categoria', 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        try {
            $tenantId = $this->tenantId();
            $currentMonth = now()->month;
            $currentYear = now()->year;

            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                $monthCondition = "CAST(strftime('%m', expense_date) AS INTEGER) = ? AND CAST(strftime('%Y', expense_date) AS INTEGER) = ?";
            } else {
                $monthCondition = 'MONTH(expense_date) = ? AND YEAR(expense_date) = ?';
            }

            $p = ExpenseStatus::PENDING->value;
            $rv = ExpenseStatus::REVIEWED->value;
            $a = ExpenseStatus::APPROVED->value;
            $rm = ExpenseStatus::REIMBURSED->value;
            $rj = ExpenseStatus::REJECTED->value;

            $stats = Expense::where('tenant_id', $tenantId)
                ->selectRaw("
                    SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as pending_total,
                    SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as reviewed_total,
                    SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as approved_total,
                    SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as reimbursed_total,
                    SUM(CASE WHEN status != ? AND {$monthCondition} THEN amount ELSE 0 END) as month_total,
                    COUNT(*) as total_count,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as reviewed_count
                ", [$p, $rv, $a, $rm, $rj, $currentMonth, $currentYear, $p, $rv])
                ->first();

            $summaryData = [
                'pending' => bcadd((string) ($stats->pending_total ?? 0), '0', 2),
                'reviewed' => bcadd((string) ($stats->reviewed_total ?? 0), '0', 2),
                'approved' => bcadd((string) ($stats->approved_total ?? 0), '0', 2),
                'month_total' => bcadd((string) ($stats->month_total ?? 0), '0', 2),
                'reimbursed' => bcadd((string) ($stats->reimbursed_total ?? 0), '0', 2),
                'total_count' => (int) ($stats->total_count ?? 0),
                'pending_count' => (int) ($stats->pending_count ?? 0),
                'reviewed_count' => (int) ($stats->reviewed_count ?? 0),
            ];

            return response()->json([
                'data' => $summaryData,
                ...$summaryData,
            ]);
        } catch (\Exception $e) {
            Log::error('Expense summary failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar resumo', 500);
        }
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        try {
            $tenantId = $this->tenantId();
            $query = Expense::with(['category:id,name', 'creator:id,name', 'workOrder:id,number,os_number', 'approver:id,name', 'chartOfAccount:id,code,name,type'])
                ->where('tenant_id', $tenantId);

            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($catId = $request->get('expense_category_id')) {
                $query->where('expense_category_id', $catId);
            }
            if ($from = $request->get('date_from')) {
                $query->where('expense_date', '>=', $from);
            }
            if ($to = $request->get('date_to')) {
                $query->where('expense_date', '<=', $to);
            }
            if ($userId = $request->get('created_by')) {
                $query->where('created_by', $userId);
            }

            $expenses = $query->orderByDesc('expense_date')->get();

            $statusLabels = collect(ExpenseStatus::cases())
                ->mapWithKeys(fn (ExpenseStatus $s) => [$s->value => $s->label()])
                ->all();

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="despesas_'.now()->format('Y-m-d').'.csv"',
            ];

            return response()->stream(function () use ($expenses, $statusLabels) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($out, ['ID', 'Descricao', 'Valor', 'Data', 'Status', 'Categoria', 'Conta Contabil', 'Responsavel', 'Aprovador', 'OS', 'Forma Pgto', 'Km Qtde', 'Km Valor', 'Km Cobrado Cliente', 'Observacoes'], ';');

                foreach ($expenses as $exp) {
                    $statusKey = $exp->status instanceof ExpenseStatus ? $exp->status->value : $exp->status;
                    fputcsv($out, [
                        $exp->id,
                        $exp->description,
                        number_format((float) $exp->amount, 2, ',', '.'),
                        $exp->expense_date?->format('d/m/Y'),
                        $statusLabels[$statusKey] ?? $statusKey,
                        $exp->category?->name ?? '',
                        $exp->chartOfAccount ? trim(($exp->chartOfAccount->code ?? '').' - '.($exp->chartOfAccount->name ?? ''), ' -') : '',
                        $exp->creator?->name ?? '',
                        $exp->approver?->name ?? '',
                        $exp->workOrder?->os_number ?? $exp->workOrder?->number ?? '',
                        $exp->payment_method ?? '',
                        $exp->km_quantity ? number_format((float) $exp->km_quantity, 1, ',', '.') : '',
                        $exp->km_rate ? number_format((float) $exp->km_rate, 4, ',', '.') : '',
                        $exp->km_billed_to_client ? 'Sim' : '',
                        $exp->notes ?? '',
                    ], ';');
                }
                fclose($out);
            }, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Expense export failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exportar despesas', 500);
        }
    }

    public function batchUpdateStatus(BatchUpdateExpenseStatusRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $validated = $request->validated();

            $newStatus = $validated['status'];
            $rejectionReason = trim((string) ($validated['rejection_reason'] ?? ''));

            if ($newStatus === ExpenseStatus::REJECTED->value && $rejectionReason === '') {
                return ApiResponse::message('Informe o motivo da rejeicao', 422, [
                    'errors' => ['rejection_reason' => ['O motivo da rejeicao e obrigatório para rejeição em lote.']],
                ]);
            }

            $expenses = Expense::where('tenant_id', $tenantId)
                ->whereIn('id', $validated['expense_ids'])
                ->whereIn('status', [ExpenseStatus::PENDING->value, ExpenseStatus::REVIEWED->value])
                ->get();

            if ($expenses->isEmpty()) {
                return ApiResponse::message('Nenhuma despesa pendente encontrada nos IDs informados', 422);
            }

            $processed = 0;
            $skipped = 0;

            DB::transaction(function () use ($validated, $newStatus, $rejectionReason, $request, $tenantId, &$processed, &$skipped) {
                // Re-fetch com lock dentro da transaction para proteção TOCTOU
                $expenses = Expense::where('tenant_id', $tenantId)
                    ->whereIn('id', $validated['expense_ids'])
                    ->lockForUpdate()
                    ->get();

                foreach ($expenses as $expense) {
                    // Re-validar status sob lock — outro request pode ter alterado
                    $currentStatusValue = $expense->status instanceof ExpenseStatus ? $expense->status->value : $expense->status;
                    if (! in_array($currentStatusValue, [ExpenseStatus::PENDING->value, ExpenseStatus::REVIEWED->value], true)) {
                        $skipped++;
                        continue;
                    }

                    if (in_array($newStatus, [ExpenseStatus::APPROVED->value, ExpenseStatus::REJECTED->value], true) && $expense->created_by === $request->user()->id) {
                        if (! $request->user()->hasRole(Role::SUPER_ADMIN)) {
                            $skipped++;
                            continue;
                        }
                    }

                    $oldStatus = $currentStatusValue;
                    $data = [
                        'status' => $newStatus,
                        'rejection_reason' => $newStatus === ExpenseStatus::REJECTED->value ? $rejectionReason : null,
                    ];
                    if ($newStatus === ExpenseStatus::APPROVED->value) {
                        $data['approved_by'] = $request->user()->id;
                    } elseif ($newStatus === ExpenseStatus::REVIEWED->value) {
                        $data['reviewed_by'] = $request->user()->id;
                        $data['reviewed_at'] = now();
                    }

                    $expense->forceFill($data)->save();

                    ExpenseStatusHistory::create([
                        'expense_id' => $expense->id,
                        'changed_by' => $request->user()->id,
                        'from_status' => $oldStatus,
                        'to_status' => $newStatus,
                        'reason' => $newStatus === ExpenseStatus::REJECTED->value ? $rejectionReason : null,
                    ]);

                    if ($newStatus === ExpenseStatus::APPROVED->value && $expense->affects_technician_cash && $expense->created_by) {
                        $fund = TechnicianCashFund::getOrCreate($expense->created_by, $tenantId);
                        $batchPaymentMethod = $expense->payment_method === 'corporate_card' ? 'corporate_card' : 'cash';
                        $fund->addDebit(
                            (string) $expense->amount,
                            "Despesa #{$expense->id}: {$expense->description}",
                            $expense->id,
                            $request->user()->id,
                            $expense->work_order_id,
                            allowNegative: true,
                            paymentMethod: $batchPaymentMethod,
                        );
                    }

                    $processed++;
                }
            });

            return ApiResponse::data(
                ['processed' => $processed, 'skipped' => $skipped],
                200,
                ['message' => "{$processed} despesa(s) atualizadas com sucesso".($skipped > 0 ? " ({$skipped} ignorada(s) por auto-aprovação)" : '')]
            );
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('Expense batchUpdateStatus failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao processar lote', 500);
        }
    }

    public function duplicate(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('create', Expense::class);

        try {
            if ((int) $expense->tenant_id !== $this->tenantId()) {
                return ApiResponse::message('Despesa não encontrada', 404);
            }

            $clone = DB::transaction(function () use ($expense, $request) {
                $clone = new Expense([
                    'tenant_id' => $expense->tenant_id,
                    'expense_category_id' => $expense->expense_category_id,
                    'work_order_id' => $expense->work_order_id,
                    'chart_of_account_id' => $expense->chart_of_account_id,
                    'created_by' => $request->user()->id,
                    'description' => $expense->description.' (Cópia)',
                    'amount' => $expense->amount,
                    'expense_date' => now()->toDateString(),
                    'payment_method' => $expense->payment_method,
                    'notes' => $expense->notes,
                    'affects_technician_cash' => $expense->affects_technician_cash,
                    'affects_net_value' => $expense->affects_net_value,
                    'km_quantity' => $expense->km_quantity,
                    'km_rate' => $expense->km_rate,
                    'km_billed_to_client' => $expense->km_billed_to_client,
                ]);
                $clone->forceFill(['status' => ExpenseStatus::PENDING]);
                $clone->save();

                ExpenseStatusHistory::create([
                    'expense_id' => $clone->id,
                    'changed_by' => $request->user()->id,
                    'from_status' => null,
                    'to_status' => ExpenseStatus::PENDING->value,
                    'reason' => "Duplicada da despesa #{$expense->id}",
                ]);

                return $clone;
            });

            return ApiResponse::data($clone->load(['category:id,name,color', 'creator:id,name', 'workOrder:id,number,os_number']), 201);
        } catch (\Exception $e) {
            Log::error('Expense duplicate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao duplicar despesa', 500);
        }
    }

    public function history(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        try {
            if ($expense->tenant_id !== $this->tenantId()) {
                return ApiResponse::message('Despesa não encontrada', 404);
            }

            $history = ExpenseStatusHistory::where('expense_id', $expense->id)
                ->with('changedBy:id,name')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'from_status' => $h->from_status,
                    'to_status' => $h->to_status,
                    'reason' => $h->reason,
                    'changed_by' => $h->changedBy?->name ?? 'Desconhecido',
                    'changed_at' => $h->created_at->toIso8601String(),
                ]);

            return ApiResponse::data($history);
        } catch (\Exception $e) {
            Log::error('Expense history failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar histórico', 500);
        }
    }

    /**
     * GAP-20: Conferência de despesa (review step antes da aprovação).
     * Marca a despesa como conferida, registrando quem e quando.
     */
    public function review(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('review', $expense);

        try {
            if ((int) $expense->tenant_id !== $this->tenantId()) {
                return ApiResponse::message('Despesa não encontrada', 404);
            }

            if ($expense->created_by === $request->user()->id && ! $request->user()->hasRole(Role::SUPER_ADMIN)) {
                return ApiResponse::message('Não é permitido conferir sua própria despesa', 403);
            }

            DB::transaction(function () use ($expense, $request) {
                $locked = Expense::lockForUpdate()->findOrFail($expense->id);
                if ($locked->status !== ExpenseStatus::PENDING) {
                    abort(422, 'Apenas despesas pendentes podem ser conferidas');
                }
                $locked->forceFill([
                    'status' => ExpenseStatus::REVIEWED,
                    'rejection_reason' => null,
                ]);
                $locked->fill([
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
                $locked->save();

                ExpenseStatusHistory::create([
                    'expense_id' => $locked->id,
                    'changed_by' => $request->user()->id,
                    'from_status' => ExpenseStatus::PENDING->value,
                    'to_status' => ExpenseStatus::REVIEWED->value,
                ]);
            });

            return ApiResponse::data($expense->fresh()->load(['category:id,name,color', 'approver:id,name', 'chartOfAccount:id,code,name,type']));
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Expense review failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao conferir despesa', 500);
        }
    }

    private function checkBudgetLimit(Expense $expense, int $tenantId): ?string
    {
        if (! $expense->expense_category_id) {
            return null;
        }

        $category = ExpenseCategory::where('id', $expense->expense_category_id)->where('tenant_id', $tenantId)->first();
        if (! $category || ! $category->budget_limit) {
            return null;
        }

        $currentMonth = $expense->expense_date?->month ?? now()->month;
        $currentYear = $expense->expense_date?->year ?? now()->year;

        $query = Expense::where('tenant_id', $tenantId)
            ->where('expense_category_id', $expense->expense_category_id)
            ->whereNotIn('status', [ExpenseStatus::REJECTED->value]);

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $query->whereRaw("CAST(strftime('%m', expense_date) AS INTEGER) = ?", [$currentMonth])
                ->whereRaw("CAST(strftime('%Y', expense_date) AS INTEGER) = ?", [$currentYear]);
        } else {
            $query->whereMonth('expense_date', $currentMonth)
                ->whereYear('expense_date', $currentYear);
        }

        $monthTotal = $query->sum('amount');

        if (bccomp((string) $monthTotal, (string) $category->budget_limit, 2) > 0) {
            $used = bcadd((string) $monthTotal, '0', 2);
            $limit = bcadd((string) $category->budget_limit, '0', 2);

            return "Orcamento da categoria '{$category->name}' ultrapassado: R$ {$used} de R$ {$limit}";
        }

        return null;
    }
}
