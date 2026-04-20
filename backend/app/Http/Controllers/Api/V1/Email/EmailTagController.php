<?php

namespace App\Http\Controllers\Api\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailTagRequest;
use App\Http\Requests\Email\UpdateEmailTagRequest;
use App\Http\Resources\EmailTagResource;
use App\Models\Email;
use App\Models\EmailActivity;
use App\Models\EmailTag;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailTagController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', EmailTag::class);

        try {
            $tags = EmailTag::orderBy('name')->paginate(min((int) request()->input('per_page', 25), 100));

            return ApiResponse::data($tags->map(fn ($t) => new EmailTagResource($t)));
        } catch (\Exception $e) {
            Log::error('EmailTag index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar tags', 500);
        }
    }

    public function store(StoreEmailTagRequest $request): JsonResponse
    {
        $this->authorize('create', EmailTag::class);

        try {
            DB::beginTransaction();
            $tag = EmailTag::create($request->validated());
            DB::commit();

            return ApiResponse::data(new EmailTagResource($tag), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmailTag store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar tag', 500);
        }
    }

    public function update(UpdateEmailTagRequest $request, EmailTag $emailTag): JsonResponse
    {
        $this->authorize('update', $emailTag);

        try {
            DB::beginTransaction();
            $emailTag->update($request->validated());
            DB::commit();

            return ApiResponse::data(new EmailTagResource($emailTag->fresh()));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmailTag update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar tag', 500);
        }
    }

    public function destroy(EmailTag $emailTag): JsonResponse
    {
        $this->authorize('delete', $emailTag);

        try {
            $emailTag->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('EmailTag destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir tag', 500);
        }
    }

    public function toggleTag(Request $request, Email $email, EmailTag $emailTag): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->authorize('view', $email);
            $attached = $email->tags()->toggle([
                $emailTag->id => ['tenant_id' => $email->tenant_id],
            ]);
            $action = count($attached['attached']) > 0 ? 'tag_added' : 'tag_removed';
            EmailActivity::create([
                'tenant_id' => $email->tenant_id,
                'email_id' => $email->id,
                'user_id' => auth()->id(),
                'type' => $action,
                'details' => ['tag_id' => $emailTag->id, 'tag_name' => $emailTag->name],
            ]);
            DB::commit();

            return ApiResponse::data(['attached' => count($attached['attached']) > 0]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmailTag toggleTag failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao alternar tag', 500);
        }
    }
}
