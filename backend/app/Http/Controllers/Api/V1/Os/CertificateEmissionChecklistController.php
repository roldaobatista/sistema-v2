<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificateEmissionChecklist\StoreCertificateEmissionChecklistRequest;
use App\Http\Resources\CertificateEmissionChecklistResource;
use App\Models\CertificateEmissionChecklist;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateEmissionChecklistController extends Controller
{
    public function show(Request $request, int $calibrationId): JsonResponse
    {
        abort_unless($request->user()->can('calibration.certificate.manage'), 403);

        $checklist = CertificateEmissionChecklist::where('equipment_calibration_id', $calibrationId)
            ->with('verifier:id,name')
            ->first();

        if (! $checklist) {
            return ApiResponse::message('Checklist não encontrado para esta calibração.', 404);
        }

        return (new CertificateEmissionChecklistResource($checklist))
            ->response();
    }

    public function storeOrUpdate(StoreCertificateEmissionChecklistRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['verified_by'] = $request->user()->id;
        $data['verified_at'] = now();

        // Calcular aprovação automática
        $checklist = new CertificateEmissionChecklist($data);
        $data['approved'] = $checklist->isComplete();

        $checklist = CertificateEmissionChecklist::updateOrCreate(
            [
                'equipment_calibration_id' => $data['equipment_calibration_id'],
                'tenant_id' => $data['tenant_id'],
            ],
            $data
        );

        $checklist->load('verifier:id,name');

        $status = $checklist->wasRecentlyCreated ? 201 : 200;

        return (new CertificateEmissionChecklistResource($checklist))
            ->response()
            ->setStatusCode($status);
    }
}
