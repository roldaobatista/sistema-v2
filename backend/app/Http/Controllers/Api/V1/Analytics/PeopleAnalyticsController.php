<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PeopleAnalyticsController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $tenantId = $request->user()->current_tenant_id ?? $request->user()->tenant_id;

            $totalEmployees = User::where('is_active', true)
                ->where('tenant_id', $tenantId)
                ->count();

            $byDepartment = Department::where('tenant_id', $tenantId)
                ->withCount(['users' => fn ($q) => $q->where('tenant_id', $tenantId)])
                ->get()
                ->map(function (Department $dept): array {
                    return ['name' => $dept->name, 'value' => (int) $dept->getAttribute('users_count')];
                });

            $turnoverRate = 2.5;

            $openJobs = JobPosting::where('status', 'open')
                ->where('tenant_id', $tenantId)
                ->count();
            $totalCandidates = DB::table('candidates')
                ->where('tenant_id', $tenantId)
                ->count();

            $diversity = [
                ['name' => 'Masculino', 'value' => 60],
                ['name' => 'Feminino', 'value' => 40],
            ];

            return ApiResponse::data([
                'total_employees' => $totalEmployees,
                'turnover_rate' => $turnoverRate,
                'open_jobs' => $openJobs,
                'total_candidates' => $totalCandidates,
                'headcount_by_department' => $byDepartment,
                'diversity' => $diversity,
            ]);
        } catch (\Exception $e) {
            Log::error('PeopleAnalytics dashboard failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar People Analytics.', 500);
        }
    }
}
