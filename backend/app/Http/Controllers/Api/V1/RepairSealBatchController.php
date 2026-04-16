<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RepairSeal\StoreRepairSealBatchRequest;
use App\Models\RepairSealBatch;
use App\Services\RepairSealService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RepairSealBatchController extends Controller
{
    public function __construct(
        private readonly RepairSealService $service,
    ) {}

    /**
     * Listar lotes.
     */
    public function index(Request $request)
    {
        $query = RepairSealBatch::with('receivedBy:id,name')
            ->where('tenant_id', Auth::user()->current_tenant_id)
            ->withCount('seals');

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $query->orderBy('received_at', 'desc');

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    /**
     * Registrar novo lote.
     */
    public function store(StoreRepairSealBatchRequest $request)
    {
        $validated = $request->validated();
        $validated['tenant_id'] = Auth::user()->current_tenant_id;
        $validated['received_by'] = Auth::id();

        $batch = $this->service->receiveBatch($validated);

        return ApiResponse::data($batch->load('receivedBy:id,name'), 201, [
            'message' => "Lote {$batch->batch_code} cadastrado com {$batch->quantity} itens.",
        ]);
    }

    /**
     * Detalhes do lote + selos.
     */
    public function show(int $id)
    {
        $batch = RepairSealBatch::with(['receivedBy:id,name', 'seals' => function ($q) {
            $q->with('assignedTo:id,name')->orderBy('number');
        }])
            ->where('tenant_id', Auth::user()->current_tenant_id)
            ->findOrFail($id);

        return ApiResponse::data($batch);
    }
}
