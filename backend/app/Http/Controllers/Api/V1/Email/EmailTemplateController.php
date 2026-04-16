<?php

namespace App\Http\Controllers\Api\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailTemplateRequest;
use App\Http\Requests\Email\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmailTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $templates = EmailTemplate::query()
                ->where(function ($query) {
                    $query->where('user_id', auth()->id())
                        ->orWhere('is_shared', true);
                })
                ->orderBy('name')
                ->paginate(min((int) request()->input('per_page', 25), 100));

            return ApiResponse::paginated($templates);
        } catch (\Exception $e) {
            Log::error('EmailTemplate index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar templates', 500);
        }
    }

    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            $this->authorize('view', $emailTemplate);

            return ApiResponse::data($emailTemplate);
        } catch (\Exception $e) {
            Log::error('EmailTemplate show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar template', 500);
        }
    }

    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $template = new EmailTemplate($validated);
            $template->user_id = auth()->id();

            if ($validated['is_shared'] ?? false) {
                $template->user_id = null;
            }

            $template->save();

            DB::commit();

            return ApiResponse::data($template, 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmailTemplate store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar template', 500);
        }
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->authorize('update', $emailTemplate);

            $validated = $request->validated();

            if (isset($validated['is_shared']) && $validated['is_shared']) {
                $emailTemplate->user_id = null;
            } elseif (isset($validated['is_shared']) && ! $validated['is_shared']) {
                $emailTemplate->user_id = auth()->id();
            }

            $emailTemplate->update($validated);

            DB::commit();

            return ApiResponse::data($emailTemplate->fresh());
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmailTemplate update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar template', 500);
        }
    }

    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            $this->authorize('delete', $emailTemplate);
            $emailTemplate->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('EmailTemplate destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir template', 500);
        }
    }
}
