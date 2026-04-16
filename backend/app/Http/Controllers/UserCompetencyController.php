<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserCompetencyRequest;
use App\Http\Requests\UpdateUserCompetencyRequest;
use App\Models\UserCompetency;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserCompetencyController extends Controller
{
    public function index(): JsonResponse
    {
        $competencies = UserCompetency::with(['user', 'equipment', 'supervisor'])
            ->latest()
            ->paginate(15);

        return ApiResponse::paginated($competencies);
    }

    public function store(StoreUserCompetencyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = $request->user()->current_tenant_id;

        $competency = UserCompetency::create($validated);
        $competency->load(['user', 'equipment', 'supervisor']);

        return ApiResponse::data($competency, 201);
    }

    public function show(UserCompetency $userCompetency): JsonResponse
    {
        $userCompetency->load(['user', 'equipment', 'supervisor']);

        return ApiResponse::data($userCompetency);
    }

    public function update(UpdateUserCompetencyRequest $request, UserCompetency $userCompetency): JsonResponse
    {
        $userCompetency->update($request->validated());
        $userCompetency->load(['user', 'equipment', 'supervisor']);

        return ApiResponse::data($userCompetency);
    }

    public function destroy(UserCompetency $userCompetency): JsonResponse
    {
        $userCompetency->delete();

        return ApiResponse::noContent();
    }
}
