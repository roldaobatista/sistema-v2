<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advanced\IndexCustomerDocumentRequest;
use App\Http\Requests\Advanced\StoreCustomerDocumentRequest;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Support\ApiResponse;
use App\Support\FilenameSanitizer;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CustomerDocumentController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexCustomerDocumentRequest $request, int $customerId): JsonResponse
    {
        if (! $this->customerExistsForTenant($customerId)) {
            return ApiResponse::message('Cliente não encontrado.', 404);
        }

        $validated = $request->validated();

        return ApiResponse::paginated(
            CustomerDocument::where('tenant_id', $this->tenantId())
                ->where('customer_id', $customerId)
                ->with('uploader:id,name')
                ->orderByDesc('created_at')
                ->paginate(min((int) ($validated['per_page'] ?? 20), 100))
        );
    }

    public function indexGlobal(IndexCustomerDocumentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = CustomerDocument::where('tenant_id', $this->tenantId())
            ->with(['uploader:id,name', 'customer:id,name']);

        if (! empty($validated['search'])) {
            $search = SearchSanitizer::escapeLike((string) $validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"));
            });
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')
                ->paginate(min((int) ($validated['per_page'] ?? 20), 100))
        );
    }

    public function store(StoreCustomerDocumentRequest $request, int $customerId): JsonResponse
    {
        if (! $this->customerExistsForTenant($customerId)) {
            return ApiResponse::message('Cliente não encontrado.', 404);
        }

        $validated = $request->validated();
        $path = null;

        try {
            DB::beginTransaction();
            $file = $request->file('file');
            $path = $file->store("customer-documents/{$customerId}", 'public');

            $doc = CustomerDocument::create([
                'tenant_id' => $this->tenantId(),
                'customer_id' => $customerId,
                'title' => $validated['title'],
                'type' => $validated['type'] ?? 'other',
                'file_path' => $path,
                'file_name' => FilenameSanitizer::sanitize($file->getClientOriginalName()),
                'file_size' => $file->getSize(),
                'expiry_date' => $validated['expiry_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);

            DB::commit();

            return ApiResponse::data($doc, 201, ['message' => 'Documento enviado']);
        } catch (\Exception $e) {
            DB::rollBack();
            if (is_string($path)) {
                Storage::disk('public')->delete($path);
            }

            Log::error('CustomerDocument upload failed', [
                'tenant_id' => $this->tenantId(),
                'customer_id' => $customerId,
                'user_id' => $request->user()?->id,
                'file_name' => $request->file('file') ? FilenameSanitizer::sanitize($request->file('file')->getClientOriginalName()) : null,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao enviar documento.', 500);
        }
    }

    public function destroy(CustomerDocument $document): JsonResponse
    {
        if ((int) $document->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Documento não encontrado.', 404);
        }

        try {
            DB::beginTransaction();
            Storage::disk('public')->delete($document->file_path);
            $document->delete();
            DB::commit();

            return ApiResponse::message('Documento removido.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CustomerDocument delete failed', [
                'tenant_id' => $this->tenantId(),
                'customer_document_id' => $document->id,
                'customer_id' => $document->customer_id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao remover documento.', 500);
        }
    }

    private function customerExistsForTenant(int $customerId): bool
    {
        return Customer::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($customerId)
            ->exists();
    }
}
