<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateNumberingSequenceRequest;
use App\Http\Resources\NumberingSequenceResource;
use App\Models\NumberingSequence;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NumberingSequenceController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NumberingSequence::class);
        $tenantId = $this->tenantId();

        $sequences = NumberingSequence::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderBy('entity')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($sequences->map(fn ($s) => new NumberingSequenceResource($s)));
    }

    public function update(UpdateNumberingSequenceRequest $request, NumberingSequence $numberingSequence): JsonResponse
    {
        $this->authorize('update', $numberingSequence);
        $validated = $request->validated();

        try {
            DB::transaction(fn () => $numberingSequence->update($validated));

            return ApiResponse::data(new NumberingSequenceResource($numberingSequence->fresh()));
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validacao', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('NumberingSequence update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar sequencia', 500);
        }
    }

    public function preview(Request $request, NumberingSequence $numberingSequence): JsonResponse
    {
        $this->authorize('view', $numberingSequence);

        $prefix = $request->query('prefix', $numberingSequence->prefix);
        $nextNumber = (int) $request->query('next_number', $numberingSequence->next_number);
        $padding = (int) $request->query('padding', $numberingSequence->padding);

        $preview = $prefix.str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT);

        return ApiResponse::data(['preview' => $preview]);
    }
}
