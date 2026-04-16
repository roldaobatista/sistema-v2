<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\ApprovePayrollRequest;
use App\Http\Requests\HR\StorePayrollRequest;
use App\Http\Resources\PayrollResource;
use App\Http\Resources\PayslipResource;
use App\Jobs\GenerateESocialEventsJob;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Services\PayrollService;
use App\Services\PayslipPdfService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayrollController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(private PayrollService $payrollService) {}

    /**
     * List payrolls with filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Payroll::query()
                ->with(['calculatedBy:id,name', 'approvedBy:id,name'])
                ->orderByDesc('reference_month')
                ->orderBy('type');

            if ($request->filled('reference_month')) {
                $query->forMonth($request->input('reference_month'));
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $perPage = max(1, min($request->integer('per_page', 15), 100));

            return ApiResponse::paginated($query->paginate($perPage), resourceClass: PayrollResource::class);
        } catch (\Exception $e) {
            Log::error('Payroll index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar folhas de pagamento.', 500);
        }
    }

    /**
     * Create new payroll (draft).
     */
    public function store(StorePayrollRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $tenantId = $this->resolvedTenantId();

            $payroll = $this->payrollService->createPayroll(
                $tenantId,
                $validated['reference_month'],
                $validated['type']
            );

            if (! empty($validated['notes'])) {
                $payroll->update(['notes' => $validated['notes']]);
            }

            return ApiResponse::data(new PayrollResource($payroll->load(['calculatedBy:id,name', 'approvedBy:id,name'])), 201);
        } catch (\Exception $e) {
            Log::error('Payroll store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar folha de pagamento.', 500);
        }
    }

    /**
     * Show payroll with lines.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $payroll = Payroll::with([
                'lines.user:id,name,email',
                'lines.payslip',
                'calculatedBy:id,name',
                'approvedBy:id,name',
            ])->findOrFail($id);

            return ApiResponse::data(new PayrollResource($payroll));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Folha de pagamento não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Payroll show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exibir folha de pagamento.', 500);
        }
    }

    /**
     * Calculate all lines for payroll.
     */
    public function calculate(int $id): JsonResponse
    {
        try {
            $payroll = Payroll::findOrFail($id);

            if (! in_array($payroll->status, ['draft', 'calculated'])) {
                return ApiResponse::message('Apenas folhas em rascunho ou calculadas podem ser recalculadas.', 422);
            }

            $this->payrollService->calculateAll($payroll);

            $payroll->refresh();
            $payroll->load(['lines.user:id,name,email', 'calculatedBy:id,name']);

            return ApiResponse::data(new PayrollResource($payroll));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Folha de pagamento não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Payroll calculate failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::message('Erro ao calcular folha de pagamento: '.$e->getMessage(), 500);
        }
    }

    /**
     * Approve payroll.
     */
    public function approve(int $id, ApprovePayrollRequest $request): JsonResponse
    {
        try {
            $payroll = Payroll::findOrFail($id);

            if ($payroll->status !== 'calculated') {
                return ApiResponse::message('Apenas folhas calculadas podem ser aprovadas.', 422);
            }

            $this->payrollService->approve($payroll, $request->user()->id);

            $payroll->refresh();

            try {
                GenerateESocialEventsJob::dispatch($payroll);
            } catch (\Throwable $e) {
                Log::warning('eSocial event generation failed (non-blocking)', ['error' => $e->getMessage()]);
            }

            return ApiResponse::data(new PayrollResource($payroll->load(['approvedBy:id,name'])));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Folha de pagamento não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Payroll approve failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aprovar folha de pagamento.', 500);
        }
    }

    /**
     * Mark payroll as paid.
     */
    public function markAsPaid(int $id): JsonResponse
    {
        try {
            $payroll = Payroll::findOrFail($id);

            if ($payroll->status !== 'approved') {
                return ApiResponse::message('Apenas folhas aprovadas podem ser marcadas como pagas.', 422);
            }

            $this->payrollService->markAsPaid($payroll);

            $payroll->refresh();

            return ApiResponse::data(new PayrollResource($payroll));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Folha de pagamento não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Payroll markAsPaid failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao marcar folha como paga.', 500);
        }
    }

    /**
     * Generate payslips for payroll.
     */
    public function generatePayslips(int $id): JsonResponse
    {
        try {
            $payroll = Payroll::findOrFail($id);

            if (! in_array($payroll->status, ['calculated', 'approved', 'paid'])) {
                return ApiResponse::message('A folha precisa estar calculada, aprovada ou paga para gerar holerites.', 422);
            }

            $this->payrollService->generatePayslips($payroll);

            return ApiResponse::message('Holerites gerados com sucesso.');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Folha de pagamento não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Payroll generatePayslips failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar holerites.', 500);
        }
    }

    /**
     * List payslips for current user (employee self-service).
     */
    public function employeePayslips(Request $request): JsonResponse
    {
        try {
            $query = Payslip::with(['payrollLine:id,gross_salary,net_salary,base_salary'])
                ->where('user_id', $request->user()->id)
                ->orderByDesc('reference_month');

            $perPage = max(1, min($request->integer('per_page', 12), 100));

            return ApiResponse::paginated($query->paginate($perPage), resourceClass: PayslipResource::class);
        } catch (\Exception $e) {
            Log::error('Employee payslips failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar holerites.', 500);
        }
    }

    /**
     * Show individual payslip with payroll line data.
     */
    public function showPayslip(int $id, Request $request): JsonResponse
    {
        try {
            $payslip = Payslip::with(['payrollLine', 'user:id,name,email'])
                ->where('user_id', $request->user()->id)
                ->findOrFail($id);

            // Mark as viewed
            if (! $payslip->viewed_at) {
                $payslip->update(['viewed_at' => now()]);
            }

            return ApiResponse::data(new PayslipResource($payslip));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Holerite não encontrado.', 404);
        } catch (\Exception $e) {
            Log::error('Show payslip failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exibir holerite.', 500);
        }
    }

    /**
     * GET /hr/reports/payroll-cost — Relatório de custo de folha por mês.
     */
    public function payrollCostReport(Request $request): JsonResponse
    {
        try {
            $months = $request->integer('months', 12);
            $startMonth = now()->subMonths($months)->format('Y-m');

            $payrolls = Payroll::where('reference_month', '>=', $startMonth)
                ->whereIn('status', ['calculated', 'approved', 'paid'])
                ->orderBy('reference_month')
                ->get()
                ->groupBy('reference_month')
                ->map(function ($group) {
                    return [
                        'reference_month' => $group->first()->reference_month,
                        'total_gross' => $group->sum('total_gross'),
                        'total_net' => $group->sum('total_net'),
                        'total_deductions' => $group->sum('total_deductions'),
                        'total_fgts' => $group->sum('total_fgts'),
                        'total_inss_employer' => $group->sum('total_inss_employer'),
                        'total_cost' => $group->sum('total_gross') + $group->sum('total_fgts') + $group->sum('total_inss_employer'),
                        'employee_count' => $group->sum('employee_count'),
                        'types' => $group->pluck('type')->unique()->values(),
                    ];
                })
                ->values();

            return ApiResponse::data($payrolls);
        } catch (\Exception $e) {
            Log::error('Payroll cost report failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório de custo.', 500);
        }
    }

    /**
     * GET /hr/payslips/{id}/download — Download payslip as printable HTML.
     */
    public function downloadPayslip(int $id, Request $request, PayslipPdfService $pdfService): Response
    {
        try {
            $payslip = Payslip::with('payrollLine')
                ->where('user_id', $request->user()->id)
                ->findOrFail($id);

            $html = $pdfService->generateHtml($payslip->payrollLine);

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        } catch (ModelNotFoundException $e) {
            return response('Holerite não encontrado.', 404);
        } catch (\Exception $e) {
            Log::error('Download payslip failed', ['error' => $e->getMessage()]);

            return response('Erro ao gerar holerite.', 500);
        }
    }
}
