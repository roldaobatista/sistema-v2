<?php

namespace App\Http\Controllers\Api\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailNoteRequest;
use App\Models\Email;
use App\Models\EmailActivity;
use App\Models\EmailNote;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmailNoteController extends Controller
{
    public function index(Email $email): JsonResponse
    {
        try {
            $this->authorize('view', $email);
            $notes = $email->notes()->with('user')->paginate(min((int) request()->input('per_page', 25), 100));

            return ApiResponse::data($notes);
        } catch (\Exception $e) {
            Log::error('EmailNote index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar notas.', 500);
        }
    }

    public function store(StoreEmailNoteRequest $request, Email $email): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->authorize('view', $email);
            $validated = $request->validated();
            $note = $email->notes()->create([
                'user_id' => auth()->id(),
                'content' => $validated['content'],
                'tenant_id' => $email->tenant_id,
            ]);

            EmailActivity::create([
                'tenant_id' => $email->tenant_id,
                'email_id' => $email->id,
                'user_id' => auth()->id(),
                'type' => 'note_added',
                'details' => ['note_id' => $note->id],
            ]);

            DB::commit();

            return ApiResponse::data($note->load('user'), 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmailNote store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar nota.', 500);
        }
    }

    public function destroy(EmailNote $emailNote): JsonResponse
    {
        try {
            if ($emailNote->user_id !== auth()->id()) {
                abort(403);
            }

            $emailNote->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('EmailNote destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir nota.', 500);
        }
    }
}
