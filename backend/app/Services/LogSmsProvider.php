<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Provedor de SMS que apenas registra em log (provedor não configurado).
 * Usado quando COLLECTION_SMS_DRIVER=log ou nenhum provedor real está configurado.
 */
class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $to, string $message): bool
    {
        Log::info('Collection SMS (log driver): message not sent', [
            'to' => $to,
            'message_length' => strlen($message),
        ]);

        return false;
    }
}
