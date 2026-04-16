<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\StoreFiscalInvoiceRequest;
use App\Http\Requests\Fiscal\UpdateFiscalInvoiceRequest;
use App\Http\Resources\FiscalInvoiceResource;
use App\Models\FiscalInvoice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = FiscalInvoice::where('tenant_id', $request->user()->current_tenant_id)
            ->latest()
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($items, resourceClass: FiscalInvoiceResource::class);
    }

    public function store(StoreFiscalInvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['number'] = $data['number'] ?? $this->nextInvoiceNumber($request);
        $data['total'] = $data['total'] ?? 0;
        $item = FiscalInvoice::create($data);

        return ApiResponse::data(new FiscalInvoiceResource($item), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = FiscalInvoice::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        return ApiResponse::data(new FiscalInvoiceResource($item));
    }

    public function update(UpdateFiscalInvoiceRequest $request, int $id): JsonResponse
    {
        $item = FiscalInvoice::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        $item->update($request->validated());

        return ApiResponse::data(new FiscalInvoiceResource($item));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = FiscalInvoice::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        $item->delete();

        return ApiResponse::noContent();
    }

    private function nextInvoiceNumber(Request $request): string
    {
        $tenantId = (int) $request->user()->current_tenant_id;
        $nextSequential = (int) FiscalInvoice::where('tenant_id', $tenantId)->count() + 1;

        return 'TMP-'.str_pad((string) $nextSequential, 6, '0', STR_PAD_LEFT);
    }
}
