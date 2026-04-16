<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use App\Support\BrazilPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provedor de SMS via Twilio.
 * Configure TWILIO_SID, TWILIO_AUTH_TOKEN e TWILIO_PHONE_NUMBER no .env e COLLECTION_SMS_DRIVER=twilio.
 */
class TwilioSmsProvider implements SmsProviderInterface
{
    private const API_URL = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    public function __construct(
        private ?string $sid,
        private ?string $token,
        private ?string $from
    ) {}

    public function send(string $to, string $message): bool
    {
        if (empty($this->sid) || empty($this->token) || empty($this->from)) {
            Log::warning('Twilio SMS: credentials or from number not configured');

            return false;
        }

        $normalized = $this->normalizePhone($to);
        if (! $normalized) {
            Log::warning('Twilio SMS: invalid phone number', ['to' => $to]);

            return false;
        }
        $to = $normalized;

        try {
            $response = Http::timeout(30)->withBasicAuth($this->sid, $this->token)
                ->asForm()
                ->post(sprintf(self::API_URL, $this->sid), [
                    'To' => $to,
                    'From' => $this->from,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return true;
            }
            Log::warning('Twilio SMS failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Twilio SMS exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        return BrazilPhone::e164($phone);
    }
}
