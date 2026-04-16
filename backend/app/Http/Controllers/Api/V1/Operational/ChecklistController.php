<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\StoreChecklistRequest;
use App\Http\Requests\Operational\UpdateChecklistRequest;
use App\Models\Checklist;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChecklistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('technicians.checklist.view');

            $checklists = Checklist::query()
                ->when($request->boolean('active_only'), function ($query) {
                    $query->where('is_active', true);
                })
                ->paginate(min((int) request()->input('per_page', 25), 100));

            return ApiResponse::data($checklists);
        } catch (AuthorizationException $e) {
            return ApiResponse::message('Sem permissão para acessar checklists', 403);
        } catch (\Exception $e) {
            Log::error('Checklist index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar checklists', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChecklistRequest $request): JsonResponse
    {
        try {
            $this->authorize('technicians.checklist.manage');

            DB::beginTransaction();

            $validated = $request->validated();
            $checklist = Checklist::create($validated);

            DB::commit();

            return ApiResponse::data($checklist, 201, ['message' => 'Checklist criado com sucesso']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checklist store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao criar checklist', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Checklist $checklist): JsonResponse
    {
        try {
            $this->authorize('technicians.checklist.view');

            return ApiResponse::data($checklist);
        } catch (\Exception $e) {
            return ApiResponse::message('Erro ao visualizar checklist', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChecklistRequest $request, Checklist $checklist): JsonResponse
    {
        try {
            $this->authorize('technicians.checklist.manage');

            DB::beginTransaction();

            $validated = $request->validated();
            $checklist->update($validated);

            DB::commit();

            return ApiResponse::data($checklist->fresh(), 200, ['message' => 'Checklist atualizado com sucesso']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checklist update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao atualizar checklist', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Checklist $checklist): JsonResponse
    {
        try {
            $this->authorize('technicians.checklist.manage');

            DB::beginTransaction();
            $checklist->delete();
            DB::commit();

            return ApiResponse::message('Checklist excluído com sucesso');
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao excluir checklist', 500);
        }
    }
}
