<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\BulkStatusTenantRequest;
use App\Http\Requests\Tenant\IndexTenantRequest;
use App\Http\Requests\Tenant\InviteTenantUserRequest;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateLogoRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantController extends Controller
{
    protected TenantService $service;

    public function __construct(TenantService $service)
    {
        $this->service = $service;
    }

    public function index(IndexTenantRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $tenants = $this->service->list(
                array_intersect_key($validated, array_flip(['search', 'status'])),
                $validated['per_page'] ?? 50
            );

            return ApiResponse::paginated($tenants);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao listar empresas.', 500);
        }
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $tenant = $this->service->create($validated);

            return ApiResponse::data($tenant->loadCount(['users', 'branches']), 201);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao criar empresa.', 500);
        }
    }

    public function show(Tenant $tenant): JsonResponse
    {
        try {
            $tenant->loadCount(['users', 'branches'])
                ->load(['users:id,name,email', 'branches:id,tenant_id,name,code']);

            $data = $tenant->toArray();
            $fullAddress = $tenant->full_address;
            $data['full_address'] = $fullAddress;

            return ApiResponse::data($data, 200, ['full_address' => $fullAddress]);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao carregar dados da empresa.', 500);
        }
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            $validated = $request->validated();

            $freshTenant = $this->service->update($tenant, $validated);

            return ApiResponse::data($freshTenant->loadCount(['users', 'branches']));
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao atualizar empresa.', 500);
        }
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        try {
            $result = $this->service->delete($tenant);

            if (is_array($result)) {
                return ApiResponse::message(
                    'Não é possível excluir empresa com dados vinculados.',
                    409,
                    ['dependencies' => $result]
                );
            }

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao excluir empresa.', 500);
        }
    }

    /**
     * Convidar usuário para um tenant.
     */
    public function invite(InviteTenantUserRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            $result = $this->service->inviteUser($tenant, $request->validated());

            return ApiResponse::data(['user' => $result['user']], 201, [
                'data' => ['user' => $result['user']],
                'user' => $result['user'],
                'message' => $result['is_new']
                    ? 'Usuário criado e notificação de definição de senha enviada.'
                    : 'Usuário existente vinculado à empresa.',
            ]);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? 'Os dados informados são inválidos.';

            return ApiResponse::message($firstError, 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao convidar usuário.', 500);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao convidar usuário.', 500);
        }
    }

    /**
     * Remover usuário de um tenant.
     */
    public function removeUser(Tenant $tenant, User $user): JsonResponse
    {
        try {
            $this->service->removeUser($tenant, $user, Auth::user());

            return ApiResponse::noContent();
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? 'Ação não permitida.';

            return ApiResponse::message($firstError, 422, ['errors' => $e->errors()]);
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao remover usuário.', 500);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao remover usuário.', 500);
        }
    }

    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Tenant::count(),
                'active' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
                'trial' => Tenant::where('status', Tenant::STATUS_TRIAL)->count(),
                'inactive' => Tenant::where('status', Tenant::STATUS_INACTIVE)->count(),
            ];

            return ApiResponse::data($stats, 200, [
                'data' => $stats,
                'total' => $stats['total'],
                'active' => $stats['active'],
                'trial' => $stats['trial'],
                'inactive' => $stats['inactive'],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao carregar estatísticas.', 500);
        }
    }

    public function availableRoles(Tenant $tenant): JsonResponse
    {
        try {
            $columns = ['id', 'name'];
            if (Schema::hasColumn('roles', 'display_name')) {
                $columns[] = 'display_name';
            }

            $roles = Role::where(function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)->orWhereNull('tenant_id');
            })
                ->select($columns)
                ->orderBy('name')
                ->get()
                ->map(fn ($r) => [
                    'name' => $r->name,
                    'display_name' => $r->display_name ?? $r->name,
                ]);

            return ApiResponse::data($roles, 200, ['roles' => $roles]);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao carregar papéis disponíveis.', 500);
        }
    }

    public function updateLogo(UpdateLogoRequest $request, Tenant $tenant, TenantService $service): JsonResponse
    {

        $path = $service->updateLogo($tenant, $request->file('logo'));

        return ApiResponse::data([
            'logo_path' => Storage::url($path),
            'message' => 'Logotipo atualizado com sucesso.',
        ]);
    }

    public function bulkStatus(BulkStatusTenantRequest $request, TenantService $service): JsonResponse
    {
        $validated = $request->validated();

        $count = $service->bulkStatus($validated['ids'], $validated['status']);

        return ApiResponse::message("Status alterado para {$count} empresas com sucesso.");
    }
}
