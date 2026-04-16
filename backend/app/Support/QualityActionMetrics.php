<?php

namespace App\Support;

use App\Models\CorrectiveAction;
use App\Models\QualityCorrectiveAction;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class QualityActionMetrics
{
    /**
     * @return array{open_actions:int, overdue_actions:int}
     */
    public static function dashboardCounts(int $tenantId): array
    {
        $generalOpenQuery = CorrectiveAction::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress']);

        $auditOpenQuery = QualityCorrectiveAction::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                QualityCorrectiveAction::STATUS_OPEN,
                QualityCorrectiveAction::STATUS_IN_PROGRESS,
            ]);

        return [
            'open_actions' => (clone $generalOpenQuery)->count() + (clone $auditOpenQuery)->count(),
            'overdue_actions' => (clone $generalOpenQuery)->where('deadline', '<', now())->count()
                + (clone $auditOpenQuery)->where('due_date', '<', now())->count(),
        ];
    }

    /**
     * @return array{total_open:int, overdue:int, due_7_days:int, avg_age_days:float}
     */
    public static function aging(int $tenantId): array
    {
        $general = CorrectiveAction::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress'])
            ->get(['deadline', 'created_at']);

        $audit = QualityCorrectiveAction::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                QualityCorrectiveAction::STATUS_OPEN,
                QualityCorrectiveAction::STATUS_IN_PROGRESS,
            ])
            ->get(['due_date', 'created_at']);

        $today = now();
        $nextWeek = now()->addDays(7);

        $generalTotal = $general->count();
        $auditTotal = $audit->count();
        $totalOpen = $generalTotal + $auditTotal;

        $ageSamples = $general->pluck('created_at')
            ->concat($audit->pluck('created_at'))
            ->filter()
            ->map(static fn ($createdAt) => $createdAt instanceof CarbonInterface ? $today->diffInDays($createdAt) : $today->diffInDays(Carbon::parse((string) $createdAt)));

        $overdue = $general->filter(fn (CorrectiveAction $action) => self::normalizeDate($action->deadline)?->lt($today) === true)->count()
            + $audit->filter(fn (QualityCorrectiveAction $action) => self::normalizeDate($action->due_date)?->lt($today) === true)->count();

        $dueSoon = $general->filter(fn (CorrectiveAction $action) => self::normalizeDate($action->deadline)?->between($today, $nextWeek) === true)->count()
            + $audit->filter(fn (QualityCorrectiveAction $action) => self::normalizeDate($action->due_date)?->between($today, $nextWeek) === true)->count();

        return [
            'total_open' => $totalOpen,
            'overdue' => $overdue,
            'due_7_days' => $dueSoon,
            'avg_age_days' => $ageSamples->isNotEmpty() ? round($ageSamples->avg(), 0) : 0.0,
        ];
    }

    public static function actionsForPeriod(int $tenantId, CarbonInterface $startDate): Collection
    {
        $generalActions = CorrectiveAction::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->with('responsible:id,name')
            ->get()
            ->map(static function (CorrectiveAction $action): array {
                $responsible = $action->responsible;

                return [
                    'description' => $action->nonconformity_description,
                    'type' => $action->type,
                    'deadline' => self::normalizeDate($action->deadline),
                    'status' => $action->status,
                    'responsible_name' => $responsible instanceof User ? $responsible->name : null,
                    'created_at' => $action->created_at,
                ];
            })
            ->all();

        $auditActions = QualityCorrectiveAction::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->with('responsible:id,name')
            ->get()
            ->map(static function (QualityCorrectiveAction $action): array {
                $responsible = $action->responsible;

                return [
                    'description' => $action->description,
                    'type' => 'audit',
                    'deadline' => self::normalizeDate($action->due_date),
                    'status' => $action->status,
                    'responsible_name' => $responsible instanceof User ? $responsible->name : null,
                    'created_at' => $action->created_at,
                ];
            })
            ->all();

        return collect([...$generalActions, ...$auditActions])
            ->sortByDesc(static fn (array $action) => $action['created_at'] instanceof CarbonInterface ? $action['created_at']->getTimestamp() : 0)
            ->values();
    }

    private static function normalizeDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
