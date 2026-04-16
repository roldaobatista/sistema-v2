<?php

namespace App\Http\Controllers\Api\V1\Lgpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lgpd\RespondLgpdDataRequestRequest;
use App\Http\Requests\Lgpd\StoreLgpdDataRequestRequest;
use App\Http\Resources\LgpdDataRequestResource;
use App\Models\LgpdDataRequest;
use App\Models\LgpdDpoConfig;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LgpdDataRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = LgpdDataRequest::query()
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('request_type'), fn ($q, $v) => $q->where('request_type', $v))
            ->when($request->input('holder_document'), fn ($q, $v) => $q->where('holder_document', $v))
            ->orderByDesc('created_at')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($requests, resourceClass: LgpdDataRequestResource::class);
    }

    public function store(StoreLgpdDataRequestRequest $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $dataRequest = LgpdDataRequest::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'protocol' => LgpdDataRequest::generateProtocol($tenantId),
            'status' => LgpdDataRequest::STATUS_PENDING,
            'deadline' => now()->addWeekdays(15),
            'created_by' => $request->user()->id,
        ]);

        $this->notifyDpo($tenantId, $dataRequest);

        return ApiResponse::data(new LgpdDataRequestResource($dataRequest), 201);
    }

    public function show(int $id): JsonResponse
    {
        $dataRequest = LgpdDataRequest::with(['responder', 'creator'])->findOrFail($id);

        return ApiResponse::data(new LgpdDataRequestResource($dataRequest));
    }

    public function respond(RespondLgpdDataRequestRequest $request, int $id): JsonResponse
    {
        $dataRequest = LgpdDataRequest::findOrFail($id);

        if ($dataRequest->status === LgpdDataRequest::STATUS_COMPLETED) {
            return ApiResponse::message('Solicitação já respondida.', 422);
        }

        $dataRequest->update([
            'status' => $request->input('status'),
            'response_notes' => $request->input('response_notes'),
            'responded_at' => now(),
            'responded_by' => $request->user()->id,
        ]);

        return ApiResponse::data(new LgpdDataRequestResource($dataRequest));
    }

    public function overdue(Request $request): JsonResponse
    {
        $overdue = LgpdDataRequest::where('status', LgpdDataRequest::STATUS_PENDING)
            ->where('deadline', '<', now())
            ->orderBy('deadline')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($overdue, resourceClass: LgpdDataRequestResource::class);
    }

    private function notifyDpo(int $tenantId, LgpdDataRequest $dataRequest): void
    {
        $dpoConfig = LgpdDpoConfig::where('tenant_id', $tenantId)->first();

        if (! $dpoConfig) {
            return;
        }

        try {
            Mail::raw(
                "Nova solicitação LGPD recebida.\n\n"
                ."Protocolo: {$dataRequest->protocol}\n"
                ."Tipo: {$dataRequest->request_type}\n"
                ."Titular: {$dataRequest->holder_name}\n"
                ."Documento: {$dataRequest->holder_document}\n"
                ."Prazo: {$dataRequest->deadline->format('d/m/Y')}\n\n"
                .'Acesse o sistema para responder dentro do prazo legal de 15 dias úteis.',
                function ($message) use ($dpoConfig, $dataRequest) {
                    $message->to($dpoConfig->dpo_email)
                        ->subject("LGPD - Nova Solicitação {$dataRequest->protocol}");
                }
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to notify DPO about LGPD request', [
                'protocol' => $dataRequest->protocol,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
