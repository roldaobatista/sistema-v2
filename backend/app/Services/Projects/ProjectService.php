<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectResource;
use App\Models\ProjectTimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * @phpstan-type ProjectDashboardData array{total_projects: int, active_projects: int, completed_projects: int, budget_total: float, spent_total: float, average_progress: float, status_breakdown: array<array-key, int>}
 * @phpstan-type ProjectGanttData array{project: array{id: int, name: string, status: string, start_date: mixed, end_date: mixed}, milestones: \Illuminate\Support\Collection<int, mixed>, resources: \Illuminate\Support\Collection<int, mixed>, time_entries: \Illuminate\Support\Collection<int, mixed>}
 */
class ProjectService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function transition(Project $project, string $status): Project
    {
        $payload = ['status' => $status];

        if ($status === 'active' && $project->actual_start_date === null) {
            $payload['actual_start_date'] = now()->toDateString();
        }

        if ($status === 'completed') {
            $payload['actual_end_date'] = now()->toDateString();
            $payload['progress_percent'] = 100;
        }

        $project->update($payload);

        return $project->fresh([
            'customer:id,name,business_name',
            'crmDeal:id,title,status,value',
            'manager:id,name',
        ]);
    }

    /**
     * @return ProjectDashboardData
     */
    public function dashboard(int $tenantId): array
    {
        $projects = Project::query()->where('tenant_id', $tenantId)->get();

        return [
            'total_projects' => $projects->count(),
            'active_projects' => $projects->where('status', 'active')->count(),
            'completed_projects' => $projects->where('status', 'completed')->count(),
            'budget_total' => round((float) $projects->sum('budget'), 2),
            'spent_total' => round((float) $projects->sum('spent'), 2),
            'average_progress' => round((float) $projects->avg('progress_percent'), 2),
            'status_breakdown' => $projects->groupBy('status')->map->count()->all(),
        ];
    }

    /**
     * @return ProjectGanttData
     */
    public function gantt(Project $project): array
    {
        $project->loadMissing([
            'milestones:id,project_id,name,status,order,planned_start,planned_end,actual_start,actual_end,weight',
            'resources:id,project_id,user_id,role,allocation_percent,start_date,end_date,total_hours_logged',
            'resources.user:id,name',
            'timeEntries:id,project_id,project_resource_id,milestone_id,work_order_id,date,hours,billable',
        ]);

        /** @var EloquentCollection<int, ProjectMilestone> $milestones */
        $milestones = $project->milestones;
        /** @var EloquentCollection<int, ProjectResource> $resources */
        $resources = $project->resources;
        /** @var EloquentCollection<int, ProjectTimeEntry> $timeEntries */
        $timeEntries = $project->timeEntries;

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'start_date' => optional($project->start_date)->toDateString(),
                'end_date' => optional($project->end_date)->toDateString(),
            ],
            'milestones' => $milestones->map(fn (ProjectMilestone $milestone) => [
                'id' => $milestone->id,
                'name' => $milestone->name,
                'status' => $milestone->status,
                'order' => $milestone->order,
                'planned_start' => optional($milestone->planned_start)->toDateString(),
                'planned_end' => optional($milestone->planned_end)->toDateString(),
                'weight' => $milestone->weight,
            ])->values(),
            'resources' => $resources->map(function (ProjectResource $resource): array {
                $user = $resource->getRelationValue('user');
                $userData = $user instanceof User
                    ? ['id' => (int) $user->id, 'name' => (string) $user->name]
                    : null;

                return [
                    'id' => $resource->id,
                    'user_id' => $resource->user_id,
                    'role' => $resource->role,
                    'allocation_percent' => $resource->allocation_percent,
                    'user' => $userData,
                ];
            })->values(),
            'time_entries' => $timeEntries->map(fn (ProjectTimeEntry $entry) => [
                'id' => $entry->id,
                'project_resource_id' => $entry->project_resource_id,
                'milestone_id' => $entry->milestone_id,
                'work_order_id' => $entry->work_order_id,
                'date' => optional($entry->date)->toDateString(),
                'hours' => $entry->hours,
                'billable' => $entry->billable,
            ])->values(),
        ];
    }

    public function recalculateProgress(Project $project): void
    {
        $project->loadMissing('milestones');

        /** @var EloquentCollection<int, ProjectMilestone> $milestones */
        $milestones = $project->milestones;

        $totalWeight = (float) $milestones->sum(fn (ProjectMilestone $milestone) => (float) $milestone->weight);

        if ($totalWeight <= 0) {
            $project->update(['progress_percent' => 0]);

            return;
        }

        $completedWeight = (float) $milestones
            ->whereIn('status', ['completed', 'invoiced'])
            ->sum(fn (ProjectMilestone $milestone) => (float) $milestone->weight);

        $project->update([
            'progress_percent' => round(($completedWeight / $totalWeight) * 100, 2),
        ]);
    }

    public function recalculateSpent(Project $project): void
    {
        $project->loadMissing('timeEntries.resource', 'resources');

        /** @var EloquentCollection<int, ProjectTimeEntry> $timeEntries */
        $timeEntries = $project->timeEntries;
        /** @var EloquentCollection<int, ProjectResource> $resources */
        $resources = $project->resources;

        $totalSpent = $timeEntries
            ->where('billable', true)
            ->sum(function (ProjectTimeEntry $entry): float {
                $hourlyRate = (float) ($entry->resource->hourly_rate ?? 0);

                return (float) $entry->hours * $hourlyRate;
            });

        foreach ($resources as $resource) {
            $resource->update([
                'total_hours_logged' => round((float) $resource->timeEntries()->sum('hours'), 2),
            ]);
        }

        $project->update([
            'spent' => round((float) $totalSpent, 2),
        ]);
    }
}
