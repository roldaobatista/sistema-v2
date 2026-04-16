<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreExpenseRequest;
use App\Http\Requests\Financial\UpdateExpenseRequest;
use App\Http\Requests\Technician\IndexTechnicianExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseStatusHistory;
use App\Models\WorkOrder;
use App\Services\ExpenseService;
use App\Support\ApiResponse;
use App\Support\ExpenseReceiptStorage;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TechnicianExpenseController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(private readonly ExpenseService $expenseService) {}

    public function index(IndexTechnicianExpenseRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $query = Expense::with([
                'category:id,name,color',
                'workOrder:id,number,os_number,assigned_to',
            ])
                ->where('tenant_id', $tenantId)
                ->where('created_by', $request->user()->id);

            if ($search = $request->validated('search')) {
                $term = SearchSanitizer::escapeLike($search);
                $query->where(function ($builder) use ($term): void {
                    $builder->where('description', 'like', "%{$term}%")
                        ->orWhereHas('workOrder', function ($workOrderQuery) use ($term): void {
                            $workOrderQuery->where('number', 'like', "%{$term}%")
                                ->orWhere('os_number', 'like', "%{$term}%");
                        });
                });
            }

            if ($status = $request->validated('status')) {
                $query->where('status', $status);
            }

            if ($categoryId = $request->validated('expense_category_id')) {
                $query->where('expense_category_id', $categoryId);
            }

            if ($workOrderId = $request->validated('work_order_id')) {
                $query->where('work_order_id', $workOrderId);
            }

            if ($from = $request->validated('date_from')) {
                $query->whereDate('expense_date', '>=', $from);
            }

            if ($to = $request->validated('date_to')) {
                $query->whereDate('expense_date', '<=', $to);
            }

            $expenses = $query
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate(min((int) $request->validated('per_page', 50), 200));

            return ApiResponse::paginated($expenses, resourceClass: ExpenseResource::class);
        } catch (\Throwable $e) {
            Log::error('Technician expense index failed', [
                'user_id' => $request->user()?->id,
                'tenant_id' => $this->tenantId(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao listar suas despesas', 500);
        }
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        try {
            $this->authorizeSelfServiceAction($request, [
                'technicians.cashbox.expense.create',
                'technicians.cashbox.manage',
            ]);

            $tenantId = $this->tenantId();
            $validated = $request->validated();
            $workOrder = $this->resolveAuthorizedWorkOrder($validated['work_order_id'] ?? null, $request->user()->id, $tenantId);
            $receiptPath = ExpenseReceiptStorage::store($request->file('receipt'), $tenantId);

            try {
                $expense = DB::transaction(function () use ($validated, $tenantId, $request, $receiptPath, $workOrder) {
                    $data = $this->applyCategoryDefaults($validated, $tenantId);
                    unset($data['receipt'], $data['status']);

                    if ($receiptPath) {
                        $data['receipt_path'] = $receiptPath;
                    }

                    $expense = new Expense([
                        ...$data,
                        'tenant_id' => $tenantId,
                        'created_by' => $request->user()->id,
                        'work_order_id' => $workOrder?->id,
                    ]);
                    $expense->forceFill([
                        'status' => ExpenseStatus::PENDING,
                        'rejection_reason' => null,
                        'approved_by' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]);
                    $expense->save();

                    ExpenseStatusHistory::create([
                        'expense_id' => $expense->id,
                        'changed_by' => $request->user()->id,
                        'from_status' => null,
                        'to_status' => ExpenseStatus::PENDING->value,
                        'reason' => 'Despesa criada pelo tecnico',
                    ]);

                    return $expense;
                });
            } catch (\Throwable $e) {
                ExpenseReceiptStorage::deleteQuietly($receiptPath, [
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()?->id,
                    'action' => 'store',
                ]);

                throw $e;
            }

            $extra = $this->buildWarnings($expense);

            return ApiResponse::data(
                new ExpenseResource($expense->load(['category:id,name,color', 'workOrder:id,number,os_number'])),
                201,
                $extra
            );
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validacao', 422, ['errors' => $e->errors()]);
        } catch (\Throwable $e) {
            Log::error('Technician expense store failed', [
                'user_id' => $request->user()?->id,
                'tenant_id' => $this->tenantId(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao registrar despesa', 500);
        }
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        try {
            $this->authorizeSelfServiceAction($request, [
                'technicians.cashbox.expense.update',
                'technicians.cashbox.manage',
            ]);

            $tenantId = $this->tenantId();

            if (! $this->isOwnedExpense($expense, $request->user()->id, $tenantId)) {
                return ApiResponse::message('Despesa nao encontrada', 404);
            }

            $currentStatus = $expense->status instanceof ExpenseStatus ? $expense->status : ExpenseStatus::from($expense->status);
            if (! in_array($currentStatus, [ExpenseStatus::PENDING, ExpenseStatus::REJECTED], true)) {
                return ApiResponse::message('Somente despesas pendentes ou rejeitadas podem ser editadas', 422);
            }

            $validated = $request->validated();
            $workOrder = $this->resolveAuthorizedWorkOrder($validated['work_order_id'] ?? $expense->work_order_id, $request->user()->id, $tenantId);

            $data = $this->applyCategoryDefaults($validated, $tenantId);
            unset($data['receipt'], $data['status']);

            $currentReceiptPath = $expense->receipt_path;
            $newReceiptPath = $request->hasFile('receipt')
                ? ExpenseReceiptStorage::store($request->file('receipt'), $tenantId)
                : null;

            if ($newReceiptPath) {
                $data['receipt_path'] = $newReceiptPath;
            }

            $extraHistoryReason = null;

            try {
                DB::transaction(function () use ($expense, $data, $workOrder, $request, $currentStatus, &$extraHistoryReason) {
                    $data['work_order_id'] = $workOrder?->id;

                    if ($currentStatus === ExpenseStatus::REJECTED) {
                        $data['status'] = ExpenseStatus::PENDING;
                        $data['rejection_reason'] = null;
                        $data['approved_by'] = null;
                        $data['reviewed_by'] = null;
                        $data['reviewed_at'] = null;
                        $extraHistoryReason = 'Despesa corrigida e reenviada pelo tecnico';
                    }

                    $expense->fill($data);
                    $expense->save();

                    if ($extraHistoryReason !== null) {
                        ExpenseStatusHistory::create([
                            'expense_id' => $expense->id,
                            'changed_by' => $request->user()->id,
                            'from_status' => ExpenseStatus::REJECTED->value,
                            'to_status' => ExpenseStatus::PENDING->value,
                            'reason' => $extraHistoryReason,
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                ExpenseReceiptStorage::deleteQuietly($newReceiptPath, [
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()?->id,
                    'expense_id' => $expense->id,
                    'action' => 'update',
                ]);

                throw $e;
            }

            if ($newReceiptPath && $currentReceiptPath && $newReceiptPath !== $currentReceiptPath) {
                ExpenseReceiptStorage::deleteQuietly($currentReceiptPath, [
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()?->id,
                    'expense_id' => $expense->id,
                    'action' => 'replace',
                ]);
            }

            $expense->refresh();
            $extra = $this->buildWarnings($expense);

            return ApiResponse::data(
                new ExpenseResource($expense->load(['category:id,name,color', 'workOrder:id,number,os_number'])),
                200,
                $extra
            );
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validacao', 422, ['errors' => $e->errors()]);
        } catch (\Throwable $e) {
            Log::error('Technician expense update failed', [
                'user_id' => $request->user()?->id,
                'tenant_id' => $this->tenantId(),
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao atualizar despesa', 500);
        }
    }

    public function destroy(Expense $expense, Request $request): JsonResponse
    {
        try {
            $this->authorizeSelfServiceAction($request, [
                'technicians.cashbox.expense.delete',
                'technicians.cashbox.manage',
            ]);

            $tenantId = $this->tenantId();

            if (! $this->isOwnedExpense($expense, $request->user()->id, $tenantId)) {
                return ApiResponse::message('Despesa nao encontrada', 404);
            }

            $status = $expense->status instanceof ExpenseStatus ? $expense->status : ExpenseStatus::from($expense->status);
            if (! in_array($status, [ExpenseStatus::PENDING, ExpenseStatus::REJECTED], true)) {
                return ApiResponse::message('Somente despesas pendentes ou rejeitadas podem ser excluidas', 409);
            }

            $receiptPath = $expense->receipt_path;

            DB::transaction(function () use ($expense) {
                $expense->delete();
            });

            ExpenseReceiptStorage::deleteQuietly($receiptPath, [
                'tenant_id' => $tenantId,
                'user_id' => $request->user()?->id,
                'expense_id' => $expense->id,
                'action' => 'destroy',
            ]);

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Technician expense destroy failed', [
                'user_id' => $request->user()?->id,
                'tenant_id' => $this->tenantId(),
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir despesa', 500);
        }
    }

    private function isOwnedExpense(Expense $expense, int $userId, int $tenantId): bool
    {
        return (int) $expense->tenant_id === $tenantId
            && (int) $expense->created_by === $userId;
    }

    private function resolveAuthorizedWorkOrder(?int $workOrderId, int $userId, int $tenantId): ?WorkOrder
    {
        if (! $workOrderId) {
            return null;
        }

        $workOrder = WorkOrder::query()
            ->where('tenant_id', $tenantId)
            ->find($workOrderId);

        if (! $workOrder) {
            throw new HttpException(404, 'OS nao encontrada');
        }

        if (! $workOrder->isTechnicianAuthorized($userId)) {
            throw new HttpException(403, 'Voce nao esta autorizado a lancar despesa nesta OS');
        }

        return $workOrder;
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

    private function buildWarnings(Expense $expense): array
    {
        $extra = [];

        $duplicateCount = Expense::query()
            ->where('tenant_id', $expense->tenant_id)
            ->where('created_by', $expense->created_by)
            ->where('description', $expense->description)
            ->where('amount', $expense->amount)
            ->whereDate('expense_date', $expense->expense_date)
            ->where('id', '!=', $expense->id)
            ->count();

        if ($duplicateCount > 0) {
            $extra['_warning'] = "Possivel duplicidade: {$duplicateCount} despesa(s) com mesma descricao, valor e data.";
        }

        if ($budgetWarning = $this->expenseService->validateLimits($expense)) {
            $extra['_budget_warning'] = $budgetWarning;
        }

        return $extra;
    }

    private function authorizeSelfServiceAction(Request $request, array $permissions): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Nao autenticado.');
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'Voce nao tem permissao para executar esta acao.');
    }
}
