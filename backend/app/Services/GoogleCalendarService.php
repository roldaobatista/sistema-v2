<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integração com Google Calendar via OAuth2.
 * Sync bidirecional de eventos (AgendaItem ↔ Google Calendar).
 */
class GoogleCalendarService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private ?string $clientId = null,
        private ?string $clientSecret = null,
        private ?string $redirectUri = null,
    ) {
        $this->clientId = $clientId ?? config('services.google.client_id');
        $this->clientSecret = $clientSecret ?? config('services.google.client_secret');
        $this->redirectUri = $redirectUri ?? config('services.google.redirect_uri');
    }

    /**
     * Gera a URL de autorização OAuth2.
     */
    public function getAuthUrl(int $userId): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $userId,
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    /**
     * Troca o authorization code por tokens e salva no usuário.
     */
    public function handleCallback(string $code, User $user): bool
    {
        try {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);

            if (! $response->successful()) {
                Log::error('Google Calendar OAuth failed', ['body' => $response->body()]);

                return false;
            }

            $data = $response->json();

            $user->update([
                'google_calendar_token' => Crypt::encryptString(json_encode([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'expires_at' => now()->addSeconds($data['expires_in'])->toDateTimeString(),
                ])),
                'google_calendar_enabled' => true,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("Google Calendar callback failed for user #{$user->id}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Sincroniza um AgendaItem para o Google Calendar do usuário.
     */
    public function syncEvent(AgendaItem $item, User $user): ?string
    {
        $token = $this->getAccessToken($user);
        if (! $token) {
            return null;
        }

        $event = [
            'summary' => $item->titulo,
            'description' => $item->descricao ?? '',
            'start' => [
                'dateTime' => Carbon::parse($item->data_inicio)->toRfc3339String(),
                'timeZone' => config('app.timezone', 'America/Sao_Paulo'),
            ],
            'end' => [
                'dateTime' => Carbon::parse($item->data_fim ?? $item->data_inicio)->addHour()->toRfc3339String(),
                'timeZone' => config('app.timezone', 'America/Sao_Paulo'),
            ],
        ];

        try {
            $googleEventId = $item->google_calendar_event_id;

            if ($googleEventId) {
                // Update existing
                $response = Http::withToken($token)
                    ->put(self::CALENDAR_API."/calendars/primary/events/{$googleEventId}", $event);
            } else {
                // Create new
                $response = Http::withToken($token)
                    ->post(self::CALENDAR_API.'/calendars/primary/events', $event);
            }

            if ($response->successful()) {
                $eventId = $response->json('id');
                $item->update(['google_calendar_event_id' => $eventId]);

                return $eventId;
            }

            Log::warning('Google Calendar sync failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        } catch (\Throwable $e) {
            Log::error("Google Calendar sync failed for item #{$item->id}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Remove um evento do Google Calendar.
     */
    public function deleteEvent(string $googleEventId, User $user): bool
    {
        $token = $this->getAccessToken($user);
        if (! $token) {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->delete(self::CALENDAR_API."/calendars/primary/events/{$googleEventId}");

            return $response->successful() || $response->status() === 410;
        } catch (\Throwable $e) {
            Log::error("Google Calendar delete failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Desconecta o Google Calendar de um usuário.
     */
    public function disconnect(User $user): void
    {
        $user->update([
            'google_calendar_token' => null,
            'google_calendar_enabled' => false,
        ]);
    }

    /**
     * Obtém um access_token válido, renovando se necessário.
     */
    private function getAccessToken(User $user): ?string
    {
        if (! $user->google_calendar_token) {
            return null;
        }

        try {
            $tokenData = json_decode(Crypt::decryptString($user->google_calendar_token), true);
        } catch (\Throwable $e) {
            Log::error("Failed to decrypt Google Calendar token for user #{$user->id}");

            return null;
        }

        $expiresAt = Carbon::parse($tokenData['expires_at'] ?? now());

        if ($expiresAt->isFuture()) {
            return $tokenData['access_token'];
        }

        // Refresh
        if (empty($tokenData['refresh_token'])) {
            Log::warning("No refresh token for user #{$user->id}");

            return null;
        }

        try {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $tokenData['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);

            if (! $response->successful()) {
                Log::error('Google Calendar token refresh failed', ['body' => $response->body()]);

                return null;
            }

            $data = $response->json();
            $tokenData['access_token'] = $data['access_token'];
            $tokenData['expires_at'] = now()->addSeconds($data['expires_in'])->toDateTimeString();

            $user->update([
                'google_calendar_token' => Crypt::encryptString(json_encode($tokenData)),
            ]);

            return $data['access_token'];
        } catch (\Throwable $e) {
            Log::error("Google Calendar refresh failed for user #{$user->id}: {$e->getMessage()}");

            return null;
        }
    }
}
