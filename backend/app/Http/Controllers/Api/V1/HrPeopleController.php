<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\CompleteTrainingRequest;
use App\Http\Requests\HR\EnrollUserRequest;
use App\Http\Requests\HR\StartHrPeopleOnboardingRequest;
use App\Http\Requests\HR\StoreHrPeopleOnboardingTemplateRequest;
use App\Http\Requests\HR\StoreOnCallScheduleRequest;
use App\Http\Requests\HR\StorePerformanceReviewRequest;
use App\Http\Requests\HR\StoreTrainingCourseRequest;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HrPeopleController extends Controller
{
    use ResolvesCurrentTenant;
    // ─── #33 Banco de Horas Automático ──────────────────────────

    public function hourBankSummary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $userId = $request->input('user_id', $request->user()->id);
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $entries = DB::table('clock_entries')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->get();

        $expectedHoursPerDay = $request->input('daily_hours', 8);
        $totalWorked = 0;
        $totalExpected = 0;
        $details = [];

        foreach ($entries as $entry) {
            $worked = ($entry->total_minutes ?? 0) / 60;
            $dayOfWeek = Carbon::parse($entry->date)->dayOfWeek;
            $isWorkday = $dayOfWeek >= 1 && $dayOfWeek <= 5;
            $expected = $isWorkday ? $expectedHoursPerDay : 0;

            $totalWorked += $worked;
            $totalExpected += $expected;

            $details[] = [
                'date' => $entry->date,
                'worked_hours' => round($worked, 2),
                'expected_hours' => $expected,
                'balance' => round($worked - $expected, 2),
            ];
        }

        $balance = $totalWorked - $totalExpected;

        return ApiResponse::data([
            'user_id' => $userId,
            'period' => ['from' => $from, 'to' => $to],
            'total_worked' => round($totalWorked, 2),
            'total_expected' => round($totalExpected, 2),
            'balance_hours' => round($balance, 2),
            'balance_type' => $balance >= 0 ? 'credit' : 'debit',
            'details' => $details,
        ]);
    }

    // ─── #34 Escala de Plantão Técnico ──────────────────────────

    public function onCallSchedule(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->addDays(30)->toDateString());

        $schedule = DB::table('on_call_schedules')
            ->where('on_call_schedules.tenant_id', $tenantId)
            ->whereBetween('on_call_schedules.date', [$from, $to])
            ->join('users', 'on_call_schedules.user_id', '=', 'users.id')
            ->select('on_call_schedules.*', 'users.name as technician_name')
            ->orderBy('on_call_schedules.date')
            ->get();

        return ApiResponse::data($schedule);
    }

    public function storeOnCallSchedule(StoreOnCallScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $this->tenantId();

        foreach ($data['entries'] as $entry) {
            DB::table('on_call_schedules')->updateOrInsert(
                ['tenant_id' => $tenantId, 'date' => $entry['date'], 'shift' => $entry['shift']],
                ['user_id' => $entry['user_id'], 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return ApiResponse::message(count($data['entries']).' schedule entries saved');
    }

    // ─── #35 Avaliação de Desempenho 360° ──────────────────────

    public function performanceReviews(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        return ApiResponse::paginated(
            DB::table('performance_reviews')
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    public function storePerformanceReview(StorePerformanceReviewRequest $request): JsonResponse
    {
        $data = $request->validated();
        $scores = collect($data['scores'] ?? [])
            ->filter(fn ($score) => is_numeric($score))
            ->values();
        $avgScore = $scores->isNotEmpty()
            ? round((float) $scores->avg(), 2)
            : 0.0;

        $payload = [
            'tenant_id' => $this->tenantId(),
            'user_id' => $data['user_id'],
            'reviewer_id' => $data['reviewer_id'],
            'comments' => $data['comments'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('performance_reviews', 'period')) {
            $payload['period'] = $data['period'];
        }

        if (Schema::hasColumn('performance_reviews', 'cycle')) {
            $payload['cycle'] = $data['period'];
        }

        if (Schema::hasColumn('performance_reviews', 'scores')) {
            $payload['scores'] = json_encode($scores->all());
        }

        if (Schema::hasColumn('performance_reviews', 'average_score')) {
            $payload['average_score'] = $avgScore;
        }

        if (Schema::hasColumn('performance_reviews', 'goals')) {
            $payload['goals'] = json_encode($data['goals'] ?? []);
        }

        if (Schema::hasColumn('performance_reviews', 'ratings')) {
            $payload['ratings'] = json_encode($scores->all());
        }

        if (Schema::hasColumn('performance_reviews', 'okrs')) {
            $payload['okrs'] = json_encode($data['goals'] ?? []);
        }

        if (Schema::hasColumn('performance_reviews', 'title')) {
            $payload['title'] = $data['title'] ?? 'Performance Review';
        }

        if (Schema::hasColumn('performance_reviews', 'year')) {
            preg_match('/\d{4}/', $data['period'], $matches);
            $payload['year'] = isset($matches[0]) ? (int) $matches[0] : (int) now()->year;
        }

        if (Schema::hasColumn('performance_reviews', 'type')) {
            $payload['type'] = $data['type'] ?? '360';
        }

        if (Schema::hasColumn('performance_reviews', 'status')) {
            $payload['status'] = 'draft';
        }

        $id = DB::table('performance_reviews')->insertGetId($payload);

        return ApiResponse::data(['id' => $id, 'average_score' => $avgScore], 201);
    }

    // ─── #36 Onboarding Digital ─────────────────────────────────

    public function onboardingTemplates(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $templates = DB::table('onboarding_templates')
            ->where('tenant_id', $tenantId)->get();

        return ApiResponse::data($templates);
    }

    public function storeOnboardingTemplate(StoreHrPeopleOnboardingTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $payload = [
            'tenant_id' => $this->tenantId(),
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('onboarding_templates', 'role') && Schema::hasColumn('onboarding_templates', 'steps')) {
            $payload['role'] = $data['role'];
            $payload['steps'] = json_encode($data['steps']);
        } else {
            $payload['type'] = $data['role'];
            $payload['default_tasks'] = json_encode(
                collect($data['steps'])
                    ->map(fn (array $step) => [
                        'title' => $step['title'],
                        'description' => $step['description'] ?? null,
                        'days_offset' => $step['days_offset'] ?? 0,
                    ])
                    ->values()
                    ->all()
            );
            $payload['is_active'] = true;
        }

        $id = DB::table('onboarding_templates')->insertGetId($payload);

        return ApiResponse::data(['id' => $id], 201);
    }

    public function startOnboarding(StartHrPeopleOnboardingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $template = DB::table('onboarding_templates')
            ->where('id', $data['template_id'])
            ->where('tenant_id', $this->tenantId())
            ->first();
        if (! $template) {
            return ApiResponse::message('Template not found', 404);
        }

        $steps = [];
        if (isset($template->steps)) {
            $steps = json_decode((string) $template->steps, true) ?? [];
        } elseif (isset($template->default_tasks)) {
            $steps = collect(json_decode((string) $template->default_tasks, true) ?? [])
                ->map(fn (array $task) => [
                    'title' => $task['title'] ?? 'Untitled',
                    'description' => $task['description'] ?? null,
                    'days_offset' => (int) ($task['days_offset'] ?? 0),
                ])
                ->all();
        }

        $startDate = now();

        $onboardingId = DB::table('onboarding_processes')->insertGetId([
            'tenant_id' => $this->tenantId(),
            'user_id' => $data['user_id'],
            'template_id' => $data['template_id'],
            'status' => 'in_progress',
            'started_at' => $startDate,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($steps as $i => $step) {
            DB::table('onboarding_steps')->insert([
                'onboarding_process_id' => $onboardingId,
                'title' => $step['title'],
                'description' => $step['description'] ?? null,
                'due_date' => $startDate->copy()->addDays($step['days_offset'])->toDateString(),
                'position' => $i + 1,
                'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ApiResponse::data(['onboarding_id' => $onboardingId, 'steps' => count($steps)], 201);
    }

    // ─── #37 Gestão de Treinamentos e Certificações ────────────

    public function trainingCourses(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $courses = DB::table('training_courses')
            ->where('tenant_id', $tenantId)
            ->paginate(20);

        return ApiResponse::paginated($courses);
    }

    public function storeTrainingCourse(StoreTrainingCourseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->tenantId();
        $id = DB::table('training_courses')->insertGetId(array_merge($data, [
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return ApiResponse::data(['id' => $id], 201);
    }

    public function enrollUser(EnrollUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $id = DB::table('training_enrollments')->insertGetId([
            'tenant_id' => $this->tenantId(),
            'user_id' => $data['user_id'],
            'course_id' => $data['course_id'],
            'status' => 'enrolled',
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::data(['enrollment_id' => $id], 201);
    }

    public function completeTraining(CompleteTrainingRequest $request, int $enrollmentId): JsonResponse
    {
        $request->validated();
        $enrollment = DB::table('training_enrollments')
            ->where('id', $enrollmentId)
            ->where('tenant_id', $this->tenantId())
            ->first();
        if (! $enrollment) {
            return ApiResponse::message('Registro não encontrado', 404);
        }

        $course = DB::table('training_courses')
            ->where('id', $enrollment->course_id)
            ->where('tenant_id', $this->tenantId())
            ->first();
        $validityMonths = $course->certification_validity_months ?? null;

        DB::table('training_enrollments')->where('id', $enrollmentId)->update([
            'status' => 'completed',
            'completed_at' => now(),
            'score' => $request->input('score'),
            'certification_number' => $request->input('certification_number'),
            'certification_expires_at' => $validityMonths ? now()->addMonths($validityMonths)->toDateString() : null,
            'updated_at' => now(),
        ]);

        return ApiResponse::message('Training completed');
    }
}
