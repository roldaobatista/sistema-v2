<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InmetroSeal;
use App\Models\PseiSubmission;
use App\Services\PseiSealSubmissionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PseiSubmissionController extends Controller
{
    public function __construct(
        private readonly PseiSealSubmissionService $service,
    ) {}

    /**
     * Listar submissões.
     */
    public function index(Request $request)
    {
        $query = PseiSubmission::with(['seal:id,number,type', 'workOrder:id,number,os_number', 'submittedBy:id,name'])
            ->where('tenant_id', Auth::user()->current_tenant_id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->seal_id) {
            $query->where('seal_id', $request->seal_id);
        }

        $query->orderBy('created_at', 'desc');

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 50), 100)));
    }

    /**
     * Detalhes de uma submissão.
     */
    public function show(int $id)
    {
        $submission = PseiSubmission::with(['seal', 'workOrder', 'equipment', 'submittedBy:id,name'])
            ->where('tenant_id', Auth::user()->current_tenant_id)
            ->findOrFail($id);

        return ApiResponse::data($submission);
    }

    /**
     * Reenviar manualmente ao PSEI.
     */
    public function retry(int $sealId)
    {
        $seal = InmetroSeal::where('tenant_id', Auth::user()->current_tenant_id)
            ->findOrFail($sealId);

        $submission = $this->service->submitSeal($seal, PseiSubmission::TYPE_MANUAL, Auth::id());

        return ApiResponse::data($submission, 200, ['message' => 'Submissão ao PSEI iniciada.']);
    }
}
