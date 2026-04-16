<?php

namespace App\Http\Controllers\Api\V1\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreDocumentVersionRequest;
use App\Http\Requests\Quality\UpdateDocumentVersionRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentVersionController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('document_versions')
            ->where('tenant_id', $this->tenantId())
            ->when($request->get('search'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->get('document_type'), fn ($q, $t) => $q->where('category', $t))
            ->when($request->get('status'), fn ($q, $st) => $q->where('status', $st));

        return ApiResponse::paginated($query->orderByDesc('version')->paginate(min((int) $request->get('per_page', 20), 100)));
    }

    public function store(StoreDocumentVersionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['file']);

        try {
            $filePath = null;
            if ($request->hasFile('file')) {
                $filePath = $request->file('file')->store('documents', 'private');
            }

            // Map validated field names to DB column names
            $category = $validated['document_type'] ?? 'procedure';
            unset($validated['document_type']);

            $versionNumber = $validated['version_number'] ?? null;
            unset($validated['version_number']);

            if ($versionNumber === null) {
                $latestVersion = DB::table('document_versions')
                    ->where('tenant_id', $this->tenantId())
                    ->where('title', $validated['title'])
                    ->max('version');
                $versionNumber = (string) (((int) $latestVersion) + 1);
            }

            // Generate document_code
            $maxCode = DB::table('document_versions')
                ->where('tenant_id', $this->tenantId())
                ->max('id');
            $documentCode = 'DOC-'.str_pad((string) (((int) $maxCode) + 1), 5, '0', STR_PAD_LEFT);

            $id = DB::table('document_versions')->insertGetId([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'category' => $category,
                'version' => $versionNumber,
                'document_code' => $documentCode,
                'file_path' => $filePath,
                'status' => 'draft',
                'tenant_id' => $this->tenantId(),
                'created_by' => auth()->id(),
                'effective_date' => $validated['effective_date'] ?? null,
                'review_date' => $validated['review_date'] ?? null,
                'approved_by' => $validated['approved_by'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ApiResponse::data(DB::table('document_versions')->find($id), 201);
        } catch (\Throwable $e) {
            Log::error('DocumentVersion store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar versão', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $doc = DB::table('document_versions')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (! $doc) {
            return ApiResponse::message('Documento não encontrado', 404);
        }

        return ApiResponse::data($doc);
    }

    public function update(UpdateDocumentVersionRequest $request, int $id): JsonResponse
    {
        $doc = DB::table('document_versions')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (! $doc) {
            return ApiResponse::message('Documento não encontrado', 404);
        }

        $validated = $request->validated();
        DB::table('document_versions')->where('id', $id)->update([...$validated, 'updated_at' => now()]);

        return ApiResponse::data(DB::table('document_versions')->find($id));
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = DB::table('document_versions')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->delete();

        return $deleted
            ? ApiResponse::noContent()
            : ApiResponse::message('Documento não encontrado', 404);
    }
}
