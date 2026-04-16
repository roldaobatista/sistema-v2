<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\ConsultaPublicaRequest;
use App\Services\Fiscal\FiscalComplianceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * #19 — Public (no auth) controller for DANFE consultation.
 */
class FiscalPublicController extends Controller
{
    public function __construct(private FiscalComplianceService $compliance) {}

    /**
     * Public DANFE lookup by access key.
     */
    public function consultaPublica(ConsultaPublicaRequest $request): JsonResponse
    {
        $note = $this->compliance->consultaPublica($request->validated()['chave_acesso']);

        if (! $note) {
            return ApiResponse::message('Nota fiscal não encontrada', 404);
        }

        return ApiResponse::data([
            'type' => $note->type,
            'number' => $note->number,
            'series' => $note->series,
            'access_key' => $note->access_key,
            'status' => $note->status,
            'total_amount' => $note->total_amount,
            'issued_at' => $note->issued_at?->format('d/m/Y H:i'),
            'nature_of_operation' => $note->nature_of_operation,
        ]);
    }
}
