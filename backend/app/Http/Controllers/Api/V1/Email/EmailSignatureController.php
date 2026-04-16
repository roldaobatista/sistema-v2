<?php

namespace App\Http\Controllers\Api\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailSignatureRequest;
use App\Http\Requests\Email\UpdateEmailSignatureRequest;
use App\Models\EmailSignature;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmailSignatureController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            $signatures = EmailSignature::where('user_id', auth()->id())
                ->with('account')
                ->paginate(min((int) request()->input('per_page', 25), 100));

            return ApiResponse::paginated($signatures);
        } catch (\Exception $e) {
            Log::error('EmailSignature index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar assinaturas', 500);
        }
    }

    public function store(StoreEmailSignatureRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $signature = new EmailSignature($validated);
            $signature->tenant_id = $this->tenantId();
            $signature->user_id = auth()->id();
            $signature->save();

            if ($signature->is_default) {
                EmailSignature::where('user_id', auth()->id())
                    ->where('id', '!=', $signature->id)
                    ->where('email_account_id', $signature->email_account_id)
                    ->update(['is_default' => false]);
            }

            DB::commit();

            return ApiResponse::data($signature, 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmailSignature store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar assinatura', 500);
        }
    }

    public function update(UpdateEmailSignatureRequest $request, EmailSignature $emailSignature): JsonResponse
    {
        if ((int) $emailSignature->user_id !== (int) auth()->id()) {
            abort(403);
        }

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $emailSignature->update($validated);

            if ($emailSignature->is_default) {
                EmailSignature::where('user_id', auth()->id())
                    ->where('id', '!=', $emailSignature->id)
                    ->where('email_account_id', $emailSignature->email_account_id)
                    ->update(['is_default' => false]);
            }

            DB::commit();

            return ApiResponse::data($emailSignature->fresh());
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmailSignature update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar assinatura', 500);
        }
    }

    public function destroy(EmailSignature $emailSignature): JsonResponse
    {
        if ((int) $emailSignature->user_id !== (int) auth()->id()) {
            abort(403);
        }

        try {
            $emailSignature->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('EmailSignature destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir assinatura', 500);
        }
    }
}
