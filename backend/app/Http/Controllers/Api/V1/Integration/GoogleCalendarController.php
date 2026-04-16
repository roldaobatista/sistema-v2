<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\HandleGoogleCalendarCallbackRequest;
use App\Services\GoogleCalendarService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleCalendarController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly GoogleCalendarService $calendarService,
    ) {}

    /**
     * Status da conexão Google Calendar do usuário.
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $connected = ! empty($user->google_calendar_token);
            $data = [
                'connected' => $connected,
                'email' => $user->google_calendar_email ?? null,
                'last_sync' => $user->google_calendar_synced_at?->diffForHumans() ?? null,
                'events_synced' => $connected
                    ? $user->agendaItems()->whereNotNull('google_event_id')->count()
                    : 0,
            ];

            return ApiResponse::data($data);
        } catch (\Throwable $e) {
            Log::error('Google Calendar status failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao verificar status', 500);
        }
    }

    /**
     * URL de autorização OAuth2.
     */
    public function authUrl(Request $request): JsonResponse
    {
        try {
            $url = $this->calendarService->getAuthUrl($request->user());

            return ApiResponse::data(['url' => $url]);
        } catch (\Throwable $e) {
            Log::error('Google Calendar auth URL failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar URL de autorização', 500);
        }
    }

    /**
     * Callback OAuth2 — troca código por tokens.
     */
    public function callback(HandleGoogleCalendarCallbackRequest $request): JsonResponse
    {
        try {
            $this->calendarService->handleCallback($request->user(), $request->input('code'));

            return ApiResponse::message('Google Calendar conectado com sucesso');
        } catch (\Throwable $e) {
            Log::error('Google Calendar callback failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao conectar Google Calendar', 500);
        }
    }

    /**
     * Desconectar Google Calendar.
     */
    public function disconnect(Request $request): JsonResponse
    {
        try {
            $this->calendarService->disconnect($request->user());

            return ApiResponse::message('Google Calendar desconectado');
        } catch (\Throwable $e) {
            Log::error('Google Calendar disconnect failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao desconectar', 500);
        }
    }

    /**
     * Forçar sincronização manual.
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $result = $this->calendarService->syncAll($request->user());

            return ApiResponse::data($result, 200, ['message' => 'Sincronização concluída']);
        } catch (\Throwable $e) {
            Log::error('Google Calendar sync failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao sincronizar', 500);
        }
    }
}
