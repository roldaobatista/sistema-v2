<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\CheckBiometricConsentRequest;
use App\Http\Requests\Journey\GrantBiometricConsentRequest;
use App\Http\Requests\Journey\IndexBiometricConsentRequest;
use App\Http\Requests\Journey\RevokeBiometricConsentRequest;
use App\Models\User;
use App\Services\Journey\BiometricComplianceService;

class BiometricConsentController extends Controller
{
    public function __construct(
        private BiometricComplianceService $complianceService,
    ) {}

    /**
     * @return mixed
     */
    public function index(IndexBiometricConsentRequest $request)
    {
        $userId = $request->input('user_id', $request->user()->id);
        /** @var User $user */
        $user = User::findOrFail($userId);

        return response()->json([
            'data' => $this->complianceService->getConsentsForUser($user, $request->tenantId()),
        ]);
    }

    /**
     * @return mixed
     */
    public function grant(GrantBiometricConsentRequest $request)
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = User::findOrFail($validated['user_id']);

        $consent = $this->complianceService->grantConsent(
            $user,
            $validated['data_type'],
            $validated['legal_basis'],
            $validated['purpose'],
            $validated['alternative_method'] ?? null,
            $validated['retention_days'] ?? 365,
            $request->tenantId(),
        );

        return response()->json(['data' => $consent], 201);
    }

    /**
     * @return mixed
     */
    public function revoke(RevokeBiometricConsentRequest $request)
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = User::findOrFail($validated['user_id']);
        $result = $this->complianceService->revokeConsent($user, $validated['data_type'], $request->tenantId());

        if (! $result) {
            return response()->json(['message' => 'Nenhum consentimento ativo encontrado.'], 422);
        }

        return response()->json(['message' => 'Consentimento revogado com sucesso.']);
    }

    /**
     * @return mixed
     */
    public function check(CheckBiometricConsentRequest $request)
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = User::findOrFail($validated['user_id']);
        $tenantId = $request->tenantId();
        $hasConsent = $this->complianceService->hasActiveConsent($user, $validated['data_type'], $tenantId);
        $alternative = $this->complianceService->getAlternativeMethod($user, $validated['data_type'], $tenantId);

        return response()->json([
            'data' => [
                'has_consent' => $hasConsent,
                'alternative_method' => $alternative,
            ],
        ]);
    }
}
