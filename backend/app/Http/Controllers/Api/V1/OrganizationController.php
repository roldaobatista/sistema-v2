<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreDepartmentRequest;
use App\Http\Requests\Organization\StorePositionRequest;
use App\Http\Requests\Organization\UpdateDepartmentRequest;
use App\Http\Requests\Organization\UpdatePositionRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\PositionResource;
use App\Models\Department;
use App\Models\Position;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    use ResolvesCurrentTenant;

    // Departments
    public function indexDepartments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        try {
            $perPage = max(1, min((int) $request->query('per_page', 100), 100));
            $departments = Department::with(['manager', 'parent', 'positions'])
                ->withCount('users')
                ->paginate($perPage);

            return ApiResponse::paginated($departments, resourceClass: DepartmentResource::class);
        } catch (\Exception $e) {
            Log::error('Organization indexDepartments failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar departamentos', 500);
        }
    }

    public function storeDepartment(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $dept = Department::create($validated + ['tenant_id' => $this->tenantId()]);

            DB::commit();

            return ApiResponse::data(new DepartmentResource($dept), 201, ['message' => 'Departamento criado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Organization storeDepartment failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar departamento', 500);
        }
    }

    public function updateDepartment(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        try {
            DB::beginTransaction();

            $department->update($request->validated());

            DB::commit();

            return ApiResponse::data(new DepartmentResource($department), 200, ['message' => 'Departamento atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Organization updateDepartment failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar departamento', 500);
        }
    }

    public function destroyDepartment(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        try {
            if ($department->children()->exists() || $department->users()->exists()) {
                return ApiResponse::message('Não é possível excluir departamento com filhos ou usuários', 409);
            }

            $department->delete();

            return ApiResponse::message('Departamento excluído');
        } catch (\Exception $e) {
            Log::error('Organization destroyDepartment failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir departamento', 500);
        }
    }

    // Positions
    public function indexPositions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Position::class);

        try {
            $perPage = max(1, min((int) $request->query('per_page', 100), 100));
            $positions = Position::with('department')->paginate($perPage);

            return ApiResponse::paginated($positions, resourceClass: PositionResource::class);
        } catch (\Exception $e) {
            Log::error('Organization indexPositions failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar cargos', 500);
        }
    }

    public function storePosition(StorePositionRequest $request): JsonResponse
    {
        $this->authorize('create', Position::class);

        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $pos = Position::create($validated + ['tenant_id' => $this->tenantId()]);

            DB::commit();

            return ApiResponse::data(new PositionResource($pos), 201, ['message' => 'Cargo criado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Organization storePosition failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar cargo', 500);
        }
    }

    public function updatePosition(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $this->authorize('update', $position);

        try {
            DB::beginTransaction();

            $position->update($request->validated());

            DB::commit();

            return ApiResponse::data(new PositionResource($position), 200, ['message' => 'Cargo atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Organization updatePosition failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar cargo', 500);
        }
    }

    public function destroyPosition(Position $position): JsonResponse
    {
        $this->authorize('delete', $position);

        try {
            if ($position->users()->exists()) {
                return ApiResponse::message('Não é possível excluir cargo com usuários vinculados', 409);
            }

            $position->delete();

            return ApiResponse::message('Cargo excluído');
        } catch (\Exception $e) {
            Log::error('Organization destroyPosition failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir cargo', 500);
        }
    }

    // Org Chart Tree
    public function orgChart(): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        try {
            $departments = Department::with(['manager', 'positions.users'])->get();

            return ApiResponse::data(DepartmentResource::collection($departments));
        } catch (\Exception $e) {
            Log::error('Organization orgChart failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar organograma', 500);
        }
    }
}
