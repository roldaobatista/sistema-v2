<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\RecommendTechnicianRequest;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TechnicianRecommendationController extends Controller
{
    use ResolvesCurrentTenant;

    private const WEIGHT_AVAILABILITY = 100;

    private const WEIGHT_SKILL_MATCH = 10;

    private const WEIGHT_PROXIMITY_CITY = 20;

    public function recommend(RecommendTechnicianRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->resolvedTenantId();

            $validated = $request->validated();

            $service = null;
            if (! empty($validated['service_id'])) {
                $service = Service::with('skills')->find($validated['service_id']);
            }

            $start = $validated['start'];
            $end = $validated['end'];

            $technicians = User::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with(['skills', 'tenants'])
                ->get();

            $recommendations = $technicians->map(function ($tech) use ($service, $start, $end, $tenantId) {
                $score = 0;
                $details = [];

                $hasConflict = Schedule::hasConflict($tech->id, $start, $end, null, $tenantId);
                if (! $hasConflict) {
                    $score += self::WEIGHT_AVAILABILITY;
                    $details['availability'] = self::WEIGHT_AVAILABILITY;
                } else {
                    $details['conflict'] = true;

                    return [
                        'id' => $tech->id,
                        'name' => $tech->name,
                        'score' => -100,
                        'details' => $details,
                    ];
                }

                $skillScore = 0;
                if ($service && $service->skills->isNotEmpty()) {
                    foreach ($service->skills as $requiredSkill) {
                        $techSkill = $tech->skills->firstWhere('skill_id', $requiredSkill->id);
                        if ($techSkill) {
                            $pivot = $requiredSkill->getRelationValue('pivot');
                            $requiredLevel = $pivot instanceof Pivot ? (int) $pivot->getAttribute('required_level') : 1;
                            $levelFactor = $techSkill->current_level / max(1, $requiredLevel);
                            $skillScore += $levelFactor * self::WEIGHT_SKILL_MATCH;
                        }
                    }
                    $details['skill_match'] = round($skillScore, 2);
                }
                $score += $skillScore;

                return [
                    'id' => $tech->id,
                    'name' => $tech->name,
                    'score' => round($score, 2),
                    'score_details' => $details,
                ];
            });

            $sorted = $recommendations->sortByDesc('score')->values();

            return ApiResponse::data($sorted->toArray());
        } catch (\Exception $e) {
            Log::error('TechnicianRecommendation recommend failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao recomendar técnicos', 500);
        }
    }
}
