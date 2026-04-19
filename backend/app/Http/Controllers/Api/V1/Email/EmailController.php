<?php

namespace App\Http\Controllers\Api\V1\Email;

use App\Enums\AgendaItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Email\AssignEmailRequest;
use App\Http\Requests\Email\BatchEmailActionRequest;
use App\Http\Requests\Email\ComposeEmailRequest;
use App\Http\Requests\Email\CreateEmailTaskRequest;
use App\Http\Requests\Email\ForwardEmailRequest;
use App\Http\Requests\Email\LinkEmailRequest;
use App\Http\Requests\Email\ReplyEmailRequest;
use App\Http\Requests\Email\SnoozeEmailRequest;
use App\Http\Resources\EmailResource;
use App\Models\AgendaItem;
use App\Models\Email;
use App\Models\EmailActivity;
use App\Models\User;
use App\Services\Email\EmailSendService;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private EmailSendService $sendService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Email::with(['account:id,label,email_address', 'customer:id,name', 'attachments'])
            ->where('tenant_id', $this->tenantId());

        // Filters
        if ($request->filled('account_id')) {
            $query->where('email_account_id', $request->account_id);
        }
        if ($request->filled('folder')) {
            match ($request->folder) {
                'inbox' => $query->inbox(),
                'sent' => $query->sent(),
                'starred' => $query->starred(),
                'archived' => $query->where('is_archived', true),
                default => $query->inbox(),
            };
        } else {
            $query->inbox();
        }
        if ($request->filled('is_read')) {
            $request->boolean('is_read') ? $query->where('is_read', true) : $query->unread();
        }
        if ($request->filled('ai_category')) {
            $query->category($request->ai_category);
        }
        if ($request->filled('ai_priority')) {
            $query->priority($request->ai_priority);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('search')) {
            $search = SearchSanitizer::escapeLike($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('from_address', 'like', "%{$search}%")
                    ->orWhere('from_name', 'like', "%{$search}%")
                    ->orWhere('body_text', 'like', "%{$search}%");
            });
        }

        $emails = $query->orderByDesc('date')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($emails, resourceClass: EmailResource::class);
    }

    public function show(Request $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);

        $email->load(['account:id,label,email_address', 'customer', 'attachments', 'linked', 'thread']);

        if (! $email->is_read) {
            $email->update(['is_read' => true]);
        }

        return ApiResponse::data(new EmailResource($email));
    }

    public function toggleStar(Request $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);
        $email->update(['is_starred' => ! $email->is_starred]);

        return ApiResponse::data(new EmailResource($email->fresh()));
    }

    public function markRead(Request $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);
        $email->update(['is_read' => true]);

        return ApiResponse::message('Marcado como lido');
    }

    public function markUnread(Request $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);
        $email->update(['is_read' => false]);

        return ApiResponse::message('Marcado como não lido');
    }

    public function archive(Request $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);
        $email->update(['is_archived' => true]);

        return ApiResponse::message('Email arquivado');
    }

    public function reply(ReplyEmailRequest $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);

        try {
            $sentEmail = $this->sendService->reply($email, $request->validated());

            return ApiResponse::data($sentEmail, 200, ['message' => 'Resposta enviada com sucesso']);
        } catch (\Exception $e) {
            Log::error('Email reply failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Falha ao enviar resposta', 500);
        }
    }

    public function forward(ForwardEmailRequest $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);

        try {
            $sentEmail = $this->sendService->forward($email, [
                'to' => $request->to,
                'body' => $request->body ?? '',
            ]);

            return ApiResponse::data($sentEmail, 200, ['message' => 'Email encaminhado com sucesso']);
        } catch (\Exception $e) {
            Log::error('Email forward failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Falha ao encaminhar email', 500);
        }
    }

    public function compose(ComposeEmailRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $sentEmail = $this->sendService->compose(
                accountId: $validated['account_id'],
                tenantId: $this->tenantId(),
                data: $validated
            );

            return ApiResponse::data($sentEmail, 201, ['message' => 'Email enviado com sucesso']);
        } catch (\Exception $e) {
            Log::error('Email compose failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Falha ao enviar email', 500);
        }
    }

    public function createTask(CreateEmailTaskRequest $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);

        try {
            DB::beginTransaction();

            $type = match ($request->validated('type')) {
                'task' => AgendaItemType::TAREFA,
                'service_call' => AgendaItemType::CHAMADO,
                'work_order' => AgendaItemType::OS,
            };

            $item = AgendaItem::criarDeOrigem(
                model: $email,
                tipo: $type,
                title: $request->validated('title') ?? $email->subject,
                responsavelId: $request->validated('responsible_id'),
                extras: [
                    'description' => $email->ai_summary ?? mb_substr($email->body_text ?? '', 0, 500),
                    'priority' => $email->ai_priority ?? 'media',
                    'context' => "Email de: {$email->from_name} <{$email->from_address}>",
                ]
            );

            $email->update([
                'linked_type' => $item->getMorphClass(),
                'linked_id' => $item->id,
            ]);

            DB::commit();

            return ApiResponse::data($item->load('assignee'), 201, ['message' => 'Item criado a partir do email']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create task from email failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar item', 500);
        }
    }

    public function linkEntity(LinkEmailRequest $request, Email $email): JsonResponse
    {
        $this->authorizeTenant($request, $email);
        $request->validated();
        $email->update([
            'linked_type' => $request->linked_type,
            'linked_id' => $request->linked_id,
        ]);

        return ApiResponse::data($email->fresh()->load('linked'), 200, ['message' => 'Email vinculado com sucesso']);
    }

    public function stats(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $stats = [
            'total' => Email::where('tenant_id', $tenantId)->count(),
            'unread' => Email::where('tenant_id', $tenantId)->unread()->count(),
            'starred' => Email::where('tenant_id', $tenantId)->starred()->count(),
            'today' => Email::where('tenant_id', $tenantId)
                ->whereDate('date', today())
                ->count(),
            'by_category' => Email::where('tenant_id', $tenantId)
                ->whereNotNull('ai_category')
                ->selectRaw('ai_category, COUNT(*) as count')
                ->groupBy('ai_category')
                ->pluck('count', 'ai_category'),
            'by_priority' => Email::where('tenant_id', $tenantId)
                ->whereNotNull('ai_priority')
                ->selectRaw('ai_priority, COUNT(*) as count')
                ->groupBy('ai_priority')
                ->pluck('count', 'ai_priority'),
            'by_sentiment' => Email::where('tenant_id', $tenantId)
                ->whereNotNull('ai_sentiment')
                ->selectRaw('ai_sentiment, COUNT(*) as count')
                ->groupBy('ai_sentiment')
                ->pluck('count', 'ai_sentiment'),
        ];

        return ApiResponse::data($stats);
    }

    /**
     * Batch actions.
     */
    /**
     * Assign email to a user.
     */
    public function assign(AssignEmailRequest $request, Email $email): JsonResponse
    {
        $this->authorize('manage', $email);
        $validated = $request->validated();
        $userId = $validated['user_id'];

        $email->update([
            'assigned_to_user_id' => $userId,
            'assigned_at' => $userId ? now() : null,
        ]);

        EmailActivity::create([
            'tenant_id' => $email->tenant_id,
            'email_id' => $email->id,
            'user_id' => auth()->id(),
            'type' => $userId ? 'assigned' : 'unassigned',
            'details' => ['assigned_to' => $userId],
        ]);

        return ApiResponse::data(new EmailResource($email));
    }

    /**
     * Snooze email until a specific date.
     */
    public function snooze(SnoozeEmailRequest $request, Email $email): JsonResponse
    {
        $this->authorize('manage', $email);
        $validated = $request->validated();
        $email->update([
            'snoozed_until' => $validated['snoozed_until'],
        ]);

        return ApiResponse::data(new EmailResource($email));
    }

    /**
     * Tracking pixel endpoint (public).
     */
    public function track($trackingId)
    {
        $email = Email::where('tracking_id', $trackingId)->first();

        if ($email) {
            $email->increment('read_count');
            $email->update(['last_read_at' => now()]);

            // Log activity only if it's the first few reads to avoid spam
            if ($email->read_count <= 5) {
                EmailActivity::create([
                    'tenant_id' => $email->tenant_id,
                    'email_id' => $email->id,
                    'user_id' => null, // External
                    'type' => 'read_tracked',
                    'details' => ['ip' => request()->ip(), 'user_agent' => request()->userAgent()],
                ]);
            }
        }

        // Return transparent 1x1 pixel
        $pixel = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');

        return response($pixel, 200)->header('Content-Type', 'image/gif');
    }

    /**
     * Batch actions.
     */
    public function batchAction(BatchEmailActionRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $query = Email::where('tenant_id', $tenantId)->whereIn('id', $request->validated('ids'));

        match ($request->validated('action')) {
            'mark_read' => $query->update(['is_read' => true]),
            'mark_unread' => $query->update(['is_read' => false]),
            'archive' => $query->update(['is_archived' => true]),
            'star' => $query->update(['is_starred' => true]),
            'unstar' => $query->update(['is_starred' => false]),
        };

        return ApiResponse::message('Ação aplicada com sucesso');
    }

    private function authorizeTenant(Request $request, Email $email): void
    {
        abort_if(
            (int) $email->tenant_id !== $this->tenantId(),
            403,
            'Acesso negado'
        );
    }
}
