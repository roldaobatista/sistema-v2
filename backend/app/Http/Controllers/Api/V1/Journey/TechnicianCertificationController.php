<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\CheckTechnicianEligibilityRequest;
use App\Http\Requests\Journey\IndexTechnicianCertificationRequest;
use App\Http\Requests\Journey\StoreTechnicianCertificationRequest;
use App\Http\Requests\Journey\UpdateTechnicianCertificationRequest;
use App\Models\TechnicianCertification;
use App\Models\User;
use App\Services\Journey\TechnicianEligibilityService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TechnicianCertificationController extends Controller
{
    public function __construct(
        private TechnicianEligibilityService $eligibilityService,
    ) {}

    /**
     * @return mixed
     */
    public function index(IndexTechnicianCertificationRequest $request)
    {
        $query = TechnicianCertification::with('user:id,name');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->orderBy('expires_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($paginator);
    }

    /**
     * @return mixed
     */
    public function show(TechnicianCertification $technicianCertification)
    {
        return response()->json([
            'data' => $technicianCertification->load('user:id,name'),
        ]);
    }

    /**
     * @return mixed
     */
    public function store(StoreTechnicianCertificationRequest $request)
    {
        $validated = $request->validated();

        $cert = TechnicianCertification::create([
            ...$validated,
            'tenant_id' => $request->tenantId(),
            'status' => 'valid',
        ]);

        $cert->refreshStatus();

        return response()->json(['data' => $cert->fresh(['user:id,name'])], 201);
    }

    /**
     * @return mixed
     */
    public function update(UpdateTechnicianCertificationRequest $request, TechnicianCertification $technicianCertification)
    {
        $validated = $request->validated();

        $technicianCertification->update($validated);
        $technicianCertification->refreshStatus();

        return response()->json(['data' => $technicianCertification->fresh()]);
    }

    /**
     * @return mixed
     */
    public function destroy(TechnicianCertification $technicianCertification)
    {
        $technicianCertification->delete();

        return response()->noContent();
    }

    /**
     * @return mixed
     */
    public function checkEligibility(CheckTechnicianEligibilityRequest $request)
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = User::findOrFail($validated['user_id']);
        $tenantId = $request->tenantId();
        $eligible = $this->eligibilityService->isEligibleForServiceType($user, $validated['service_type'], $tenantId);
        $blocking = $this->eligibilityService->getBlockingCertifications($user, $validated['service_type'], $tenantId);

        return response()->json([
            'data' => [
                'eligible' => $eligible,
                'blocking' => $blocking->toArray(),
            ],
        ]);
    }

    /**
     * @return mixed
     */
    public function expiring(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $expiring = $this->eligibilityService->getExpiringCertifications(
            $request->user()->current_tenant_id,
            $days,
        );

        return response()->json(['data' => $expiring]);
    }
}
