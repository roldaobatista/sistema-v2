<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SubmitSatisfactionSurveyResponseRequest;
use App\Models\Customer;
use App\Models\SatisfactionSurvey;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SatisfactionSurveyController extends Controller
{
    public function show(Request $request, SatisfactionSurvey $survey): JsonResponse
    {
        if (! $this->hasValidToken($request->query('token'), $survey)) {
            return ApiResponse::message('Pesquisa não encontrada.', 404);
        }

        $survey->loadMissing(['customer:id,name', 'workOrder:id,number']);
        $customer = $survey->customer;
        $workOrder = $survey->workOrder;

        return ApiResponse::data([
            'id' => $survey->id,
            'customer' => $customer instanceof Customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
            ] : null,
            'work_order' => $workOrder instanceof WorkOrder ? [
                'id' => $workOrder->id,
                'number' => $workOrder->number,
            ] : null,
            'channel' => $survey->channel,
            'answered' => $this->isAnswered($survey),
            'nps_score' => $survey->nps_score,
            'service_rating' => $survey->service_rating,
            'technician_rating' => $survey->technician_rating,
            'timeliness_rating' => $survey->timeliness_rating,
            'comment' => $survey->comment,
        ]);
    }

    public function answer(SubmitSatisfactionSurveyResponseRequest $request, SatisfactionSurvey $survey): JsonResponse
    {
        if (! $this->hasValidToken($request->validated('token'), $survey)) {
            return ApiResponse::message('Pesquisa não encontrada.', 404);
        }

        if ($this->isAnswered($survey)) {
            return ApiResponse::message('Pesquisa já respondida.', 422);
        }

        $survey->update($request->safe()->except('token'));

        return ApiResponse::data($survey->fresh(), 200, [
            'message' => 'Pesquisa respondida com sucesso.',
        ]);
    }

    private function hasValidToken(?string $token, SatisfactionSurvey $survey): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            return (int) decrypt($token) === (int) $survey->id;
        } catch (DecryptException) {
            return false;
        }
    }

    private function isAnswered(SatisfactionSurvey $survey): bool
    {
        return $survey->nps_score !== null
            || $survey->service_rating !== null
            || $survey->technician_rating !== null
            || $survey->timeliness_rating !== null;
    }
}
