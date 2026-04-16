<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RepairSeal\AssignRepairSealsRequest;
use App\Http\Requests\RepairSeal\RegisterSealUsageRequest;
use App\Http\Requests\RepairSeal\ReportSealDamageRequest;
use App\Http\Requests\RepairSeal\ReturnRepairSealsRequest;
use App\Http\Requests\RepairSeal\TransferRepairSealsRequest;
use App\Models\InmetroSeal;
use App\Services\RepairSealService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RepairSealController extends Controller
{
    public function __construct(
        private readonly RepairSealService $service,
    ) {}

    /**
     * Listar todos os selos (paginado, com filtros).
     */
    public function index(Request $request)
    {
        $query = InmetroSeal::with(['assignedTo:id,name', 'workOrder:id,number,os_number', 'equipment:id,code,brand,model,serial_number', 'batch:id,batch_code'])
            ->where('tenant_id', Auth::user()->current_tenant_id);

        if ($request->search) {
            $query->where('number', 'like', '%'.$request->search.'%');
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->technician_id) {
            $query->where('assigned_to', $request->technician_id);
        }

        if ($request->psei_status) {
            $query->where('psei_status', $request->psei_status);
        }

        if ($request->deadline_status) {
            $query->where('deadline_status', $request->deadline_status);
        }

        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 50), 100)));
    }

    /**
     * Dashboard com stats gerais.
     */
    public function dashboard()
    {
        $stats = $this->service->getDashboardStats(Auth::user()->current_tenant_id);

        return ApiResponse::data($stats);
    }

    /**
     * Inventário do técnico logado.
     */
    public function myInventory()
    {
        $seals = $this->service->getTechnicianInventory(Auth::id());

        return ApiResponse::data($seals);
    }

    /**
     * Inventário de um técnico específico (admin).
     */
    public function technicianInventory(int $id)
    {
        $seals = $this->service->getTechnicianInventory($id);

        return ApiResponse::data($seals);
    }

    /**
     * Detalhes de um selo.
     */
    public function show(int $id)
    {
        $seal = InmetroSeal::with([
            'assignedTo:id,name',
            'workOrder:id,number,os_number',
            'equipment:id,code,brand,model,serial_number',
            'batch:id,batch_code,type',
            'assignments.technician:id,name',
            'assignments.assignedBy:id,name',
            'pseiSubmissions',
            'alerts',
        ])->where('tenant_id', Auth::user()->current_tenant_id)
            ->findOrFail($id);

        return ApiResponse::data($seal);
    }

    /**
     * Registrar uso de selo em OS.
     */
    public function registerUsage(RegisterSealUsageRequest $request)
    {
        $validated = $request->validated();

        $photoPath = $request->file('photo')->store('seals', 'public');

        $seal = $this->service->registerUsage(
            sealId: $validated['seal_id'],
            workOrderId: $validated['work_order_id'],
            equipmentId: $validated['equipment_id'],
            photoPath: $photoPath,
            userId: Auth::id(),
        );

        return ApiResponse::data($seal, 200, ['message' => 'Uso registrado com sucesso!']);
    }

    /**
     * Atribuir selos a técnico.
     */
    public function assignToTechnician(AssignRepairSealsRequest $request)
    {
        $validated = $request->validated();

        $count = $this->service->assignToTechnician(
            sealIds: $validated['seal_ids'],
            technicianId: $validated['technician_id'],
            assignedBy: Auth::id(),
        );

        return ApiResponse::message("{$count} selos atribuídos ao técnico com sucesso.");
    }

    /**
     * Transferir selos entre técnicos.
     */
    public function transfer(TransferRepairSealsRequest $request)
    {
        $validated = $request->validated();

        $count = $this->service->transferBetweenTechnicians(
            sealIds: $validated['seal_ids'],
            fromId: $validated['from_technician_id'],
            toId: $validated['to_technician_id'],
            transferredBy: Auth::id(),
        );

        return ApiResponse::message("{$count} selos transferidos com sucesso.");
    }

    /**
     * Devolver selos ao estoque.
     */
    public function returnSeals(ReturnRepairSealsRequest $request)
    {
        $validated = $request->validated();

        $count = $this->service->returnSeals(
            sealIds: $validated['seal_ids'],
            reason: $validated['reason'],
            returnedBy: Auth::id(),
        );

        return ApiResponse::message("{$count} selos devolvidos ao estoque com sucesso.");
    }

    /**
     * Reportar selo danificado ou perdido.
     */
    public function reportDamage(ReportSealDamageRequest $request, int $id)
    {
        $validated = $request->validated();

        $seal = $this->service->reportDamage(
            sealId: $id,
            status: $validated['status'],
            reason: $validated['reason'],
            reportedBy: Auth::id(),
        );

        return ApiResponse::data($seal, 200, ['message' => 'Selo reportado como '.$seal->status_label.'.']);
    }

    /**
     * Selos com prazo vencido.
     */
    public function overdue()
    {
        $seals = $this->service->getOverdueSeals(Auth::user()->current_tenant_id);

        return ApiResponse::data($seals);
    }

    /**
     * Selos aguardando envio PSEI.
     */
    public function pendingPsei()
    {
        $seals = $this->service->getPendingPseiSeals(Auth::user()->current_tenant_id);

        return ApiResponse::data($seals);
    }

    /**
     * Exportar relatório CSV.
     */
    public function export(Request $request)
    {
        $query = InmetroSeal::with(['assignedTo:id,name', 'workOrder:id,number', 'equipment:id,brand,model', 'batch:id,batch_code'])
            ->where('tenant_id', Auth::user()->current_tenant_id);

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->technician_id) {
            $query->where('assigned_to', $request->technician_id);
        }

        $seals = $query->get();

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=selos_reparo_'.date('Ymd_His').'.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($seals) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['ID', 'Tipo', 'Número', 'Lote', 'Status', 'Status PSEI', 'Técnico', 'OS', 'Equipamento', 'Data Uso', 'Prazo PSEI', 'Protocolo PSEI', 'Data Cadastro']);

            foreach ($seals as $seal) {
                fputcsv($file, [
                    $seal->id,
                    $seal->type_label,
                    $seal->number,
                    $seal->batch?->batch_code ?? '—',
                    $seal->status_label,
                    $seal->psei_status_label,
                    $seal->assignedTo?->name ?? 'N/A',
                    $seal->workOrder?->number ?? 'N/A',
                    $seal->equipment ? "{$seal->equipment->brand} {$seal->equipment->model}" : 'N/A',
                    $seal->used_at?->format('d/m/Y H:i') ?? '—',
                    $seal->deadline_at?->format('d/m/Y') ?? '—',
                    $seal->psei_protocol ?? '—',
                    $seal->created_at->format('d/m/Y H:i'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
