<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\TimeClockAdjustment;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Models\VacationBalance;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * GET /hr/dashboard/widgets
     */
    public function widgets(Request $request): JsonResponse
    {
        try {
            $today = Carbon::today();
            $currentMonth = $today->month;

            // Employees currently clocked in (no clock_out today)
            $employeesClockedIn = TimeClockEntry::whereDate('clock_in', $today)
                ->whereNull('clock_out')
                ->count();

            // Pending time clock adjustments
            $pendingAdjustments = TimeClockAdjustment::pending()
                ->count();

            // Pending leave requests
            $pendingLeaves = LeaveRequest::where('status', 'pending')
                ->count();

            // Documents expiring in the next 30 days
            $expiringDocuments30d = EmployeeDocument::whereBetween('expiry_date', [
                $today,
                $today->copy()->addDays(30),
            ])->count();

            // Vacations with deadline in the next 60 days (remaining_days is accessor, filter in PHP)
            $expiringVacations60d = VacationBalance::whereBetween('deadline', [
                $today,
                $today->copy()->addDays(60),
            ])->whereIn('status', ['available', 'partially_taken'])->count();

            // Hour bank: sum of hours per user
            $hourBankPositive = (float) DB::table('hour_bank_transactions')
                ->select(DB::raw('SUM(hours) as total'))
                ->where('tenant_id', $this->tenantId())
                ->value('total') ?? 0;

            // Current (latest) payroll
            $currentPayroll = Payroll::latest('created_at')
                ->select('id', 'reference_month', 'status', 'created_at')
                ->first();

            // Birthdays this month
            $isMySQL = DB::connection()->getDriverName() === 'mysql';
            $monthWhere = $isMySQL ? 'MONTH(birth_date) = ?' : "CAST(strftime('%m', birth_date) AS INTEGER) = ?";
            $dayOrder = $isMySQL ? 'DAY(birth_date)' : "CAST(strftime('%d', birth_date) AS INTEGER)";

            $birthdaysThisMonth = User::where('current_tenant_id', $this->tenantId())
                ->whereRaw($monthWhere, [$currentMonth])
                ->whereNotNull('birth_date')
                ->select('id', 'name', 'birth_date')
                ->orderByRaw($dayOrder)
                ->get();

            return ApiResponse::data([
                'employees_clocked_in' => $employeesClockedIn,
                'pending_adjustments' => $pendingAdjustments,
                'pending_leaves' => $pendingLeaves,
                'expiring_documents_30d' => $expiringDocuments30d,
                'expiring_vacations_60d' => $expiringVacations60d,
                'hour_bank_positive' => $hourBankPositive,
                'current_payroll' => $currentPayroll,
                'birthdays_this_month' => $birthdaysThisMonth,
            ]);
        } catch (\Throwable $e) {
            Log::error('HR Dashboard widgets error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::message('Erro ao carregar widgets do dashboard.', 500);
        }
    }

    /**
     * GET /hr/dashboard/team
     */
    public function team(Request $request): JsonResponse
    {
        try {
            $today = Carbon::today();
            $userId = auth()->id();

            $subordinates = User::where('manager_id', $userId)
                ->where('current_tenant_id', $this->tenantId())
                ->select('id', 'name')
                ->get();

            $subordinateIds = $subordinates->pluck('id');

            // Clock entries for today for all subordinates (eager batch)
            $clockEntries = TimeClockEntry::whereIn('user_id', $subordinateIds)
                ->whereDate('clock_in', $today)
                ->get()
                ->groupBy('user_id');

            // Active leave requests for today
            $activeLeaves = LeaveRequest::whereIn('user_id', $subordinateIds)
                ->where('status', 'approved')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->pluck('user_id')
                ->unique()
                ->toArray();

            $teamStatus = $subordinates->map(function ($user) use ($clockEntries, $activeLeaves) {
                $entries = $clockEntries->get($user->id, collect());

                $hasClockedIn = $entries->isNotEmpty();

                // On break: has an entry with clock_out set but also has a later entry without clock_out,
                // OR simpler: last entry has clock_out (meaning they clocked out but are still "on shift")
                // More standard: "is_on_break" = has entry where break_start is set and break_end is null
                // Since we only have clock_in/clock_out, consider on break if last entry has clock_out
                // and there's no subsequent open entry — simplified: last entry's clock_out is not null
                // and the user has clocked in today (they stepped out).
                $lastEntry = $entries->sortByDesc('clock_in')->first();
                $isOnBreak = $hasClockedIn && $lastEntry && $lastEntry->clock_out !== null;

                $isOnLeave = in_array($user->id, $activeLeaves);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'has_clocked_in_today' => $hasClockedIn,
                    'is_on_break' => $isOnBreak,
                    'is_on_leave' => $isOnLeave,
                ];
            });

            return ApiResponse::data($teamStatus);
        } catch (\Throwable $e) {
            Log::error('HR Dashboard team error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::message('Erro ao carregar status da equipe.', 500);
        }
    }
}
