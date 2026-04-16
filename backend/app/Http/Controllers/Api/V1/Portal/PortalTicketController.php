<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\AddPortalTicketMessageRequest;
use App\Http\Requests\Portal\StorePortalTicketRequest;
use App\Http\Requests\Portal\UpdatePortalTicketRequest;
use App\Models\ClientPortalUser;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortalTicketController extends Controller
{
    private function portalUser(Request $request): ClientPortalUser
    {
        $user = $request->user();

        if (! $user instanceof ClientPortalUser || ! $user->tokenCan('portal:access')) {
            abort(403, 'Acesso restrito ao portal do cliente.');
        }

        return $user;
    }

    private function findTicket(int $id, ClientPortalUser $user): ?object
    {
        return DB::table('portal_tickets')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer_id)
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->portalUser($request);

        $query = DB::table('portal_tickets')
            ->where('tenant_id', $user->tenant_id)
            ->where('customer_id', $user->customer_id)
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('search'), fn ($q, $search) => $q->where('subject', 'like', SearchSanitizer::contains($search)));

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 20), 100)));
    }

    public function store(StorePortalTicketRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->portalUser($request);
        $id = DB::transaction(function () use ($validated, $user) {
            $priority = $validated['priority'] ?? 'normal';

            $policy = DB::table('sla_policies')
                ->where('tenant_id', $user->tenant_id)
                ->where('priority', $priority)
                ->where('is_active', true)
                ->first();

            $slaDueAt = $policy ? now()->addMinutes($policy->resolution_time_minutes)->format('Y-m-d H:i:s') : null;

            $driver = DB::connection()->getDriverName();
            $castType = $driver === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
            $lastNumber = (int) (DB::table('portal_tickets')
                ->where('tenant_id', $user->tenant_id)
                ->lockForUpdate()
                ->max(DB::raw("CAST(REPLACE(ticket_number, 'TKT-', '') AS {$castType})")) ?? 0);

            $ticketNumber = 'TKT-'.str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

            return DB::table('portal_tickets')->insertGetId([
                ...$validated,
                'priority' => $priority,
                'status' => 'open',
                'customer_id' => $user->customer_id,
                'tenant_id' => $user->tenant_id,
                'created_by' => $user->id,
                'ticket_number' => $ticketNumber,
                'sla_due_at' => $slaDueAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $ticket = DB::table('portal_tickets')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        return ApiResponse::data($ticket, 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $this->portalUser($request);
        $ticket = $this->findTicket($id, $user);

        if (! $ticket) {
            return ApiResponse::message('Ticket nao encontrado', 404);
        }

        $messages = DB::table('portal_ticket_messages')
            ->where('portal_ticket_id', $id)
            ->where('is_internal', false)
            ->orderBy('created_at')
            ->get();

        $ticket->messages = $messages;

        return ApiResponse::data($ticket);
    }

    public function update(UpdatePortalTicketRequest $request, int $id): JsonResponse
    {
        $user = $this->portalUser($request);
        $ticket = $this->findTicket($id, $user);

        if (! $ticket) {
            return ApiResponse::message('Ticket nao encontrado', 404);
        }

        $validated = $request->validated();
        $resolvedStatuses = ['resolved', 'closed'];

        DB::transaction(function () use ($validated, $user, $id, $resolvedStatuses) {
            $updateData = [...$validated, 'updated_at' => now()];

            if (array_key_exists('status', $validated) && Schema::hasColumn('portal_tickets', 'resolved_at')) {
                $updateData['resolved_at'] = in_array($validated['status'], $resolvedStatuses, true)
                    ? now()
                    : null;
            }

            DB::table('portal_tickets')
                ->where('tenant_id', $user->tenant_id)
                ->where('customer_id', $user->customer_id)
                ->where('id', $id)
                ->update($updateData);
        });

        $updatedTicket = DB::table('portal_tickets')
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        return ApiResponse::data($updatedTicket);
    }

    public function addMessage(AddPortalTicketMessageRequest $request, int $id): JsonResponse
    {
        $user = $this->portalUser($request);
        $ticket = $this->findTicket($id, $user);

        if (! $ticket) {
            return ApiResponse::message('Ticket nao encontrado', 404);
        }

        if ($ticket->status === 'closed') {
            return ApiResponse::message('Tickets fechados não aceitam novas mensagens.', 422);
        }

        $validated = $request->validated();

        $messageId = DB::transaction(function () use ($id, $user, $validated) {
            return DB::table('portal_ticket_messages')->insertGetId([
                'portal_ticket_id' => $id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return ApiResponse::data(DB::table('portal_ticket_messages')->find($messageId), 201);
    }
}
