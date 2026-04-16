<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\AssessUserSkillRequest;
use App\Http\Requests\HR\StoreSkillRequest;
use App\Http\Requests\HR\UpdateSkillRequest;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SkillsController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(): JsonResponse
    {
        try {
            return ApiResponse::paginated(Skill::paginate(15));
        } catch (\Exception $e) {
            Log::error('Skills index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar competências', 500);
        }
    }

    public function store(StoreSkillRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $skill = Skill::create($validated + ['tenant_id' => $this->resolvedTenantId()]);

            DB::commit();

            return ApiResponse::data($skill, 201, ['message' => 'Competência criada']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Skills store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar competência', 500);
        }
    }

    public function show(Skill $skill): JsonResponse
    {
        try {
            return ApiResponse::data($skill);
        } catch (\Exception $e) {
            Log::error('Skills show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar competência', 500);
        }
    }

    public function update(UpdateSkillRequest $request, Skill $skill): JsonResponse
    {
        try {
            DB::beginTransaction();

            $skill->update($request->validated());

            DB::commit();

            return ApiResponse::data($skill->fresh(), 200, ['message' => 'Competência atualizada']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Skills update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar competência', 500);
        }
    }

    public function destroy(Skill $skill): JsonResponse
    {
        try {
            $skill->delete();

            return ApiResponse::message('Competência excluída');
        } catch (\Exception $e) {
            Log::error('Skills destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir competência', 500);
        }
    }

    public function matrix(): JsonResponse
    {
        try {
            $userModels = User::where('tenant_id', $this->resolvedTenantId())
                ->with(['position.skillRequirements.skill', 'skills'])
                ->get();

            $users = [];
            foreach ($userModels as $user) {
                $position = $user->position;
                $requirements = [];
                foreach (($position->skillRequirements ?? []) as $req) {
                    $requirements[] = [
                        'skill_id' => $req->skill_id,
                        'skill_name' => $req->skill?->name,
                        'required' => $req->required_level,
                    ];
                }

                $skills = [];
                foreach ($user->skills as $s) {
                    $skills[] = [
                        'skill_id' => $s->skill_id,
                        'current' => $s->current_level,
                        'assessed_at' => $s->assessed_at,
                    ];
                }

                $users[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'position' => $position?->name,
                    'requirements' => $requirements,
                    'skills' => $skills,
                ];
            }

            return ApiResponse::data($users);
        } catch (\Exception $e) {
            Log::error('Skills matrix failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar matriz de competências', 500);
        }
    }

    public function assessUser(AssessUserSkillRequest $request, $userId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $us = UserSkill::updateOrCreate(
                ['user_id' => $userId, 'skill_id' => $validated['skill_id']],
                [
                    'current_level' => $validated['level'],
                    'assessed_at' => now(),
                    'assessed_by' => auth()->id(),
                ]
            );

            DB::commit();

            return ApiResponse::data($us, 200, ['message' => 'Avaliação registrada']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Skills assessUser failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao avaliar competência', 500);
        }
    }
}
