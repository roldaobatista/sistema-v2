<?php

namespace App\Contracts;

/**
 * Provedor de envio de SMS para régua de cobrança e outros fluxos.
 * Implementações: LogSmsProvider (apenas log), TwilioSmsProvider (quando configurado).
 */
interface SmsProviderInterface
{
    /**
     * Envia SMS para o número informado.
     *
     * @param  string  $to  Número no formato E.164 (ex: +5511999999999)
     * @param  string  $message  Texto da mensagem
     * @return bool true se enviado com sucesso, false caso contrário
     */
    public function send(string $to, string $message): bool;
}
