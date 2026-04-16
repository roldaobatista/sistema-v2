<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class FiscalWebhookCallbackRequest extends FormRequest
{
    /**
     * Defense-in-depth: validate fiscal webhook secret header.
     * Primary protection is via VerifyFiscalWebhookSecret middleware on the route,
     * but this provides a second layer if the middleware is accidentally removed.
     */
    public function authorize(): bool
    {
        $expected = config('services.fiscal_external.webhook_secret');

        // In non-production without a configured secret, allow (matches middleware behavior)
        if (empty($expected)) {
            return ! app()->environment('production');
        }

        $token = $this->header('X-Fiscal-Webhook-Secret');

        return ! empty($token) && hash_equals((string) $expected, (string) $token);
    }

    public function rules(): array
    {
        return [
            'ref' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:100',
            'chave_nfe' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'protocolo' => 'nullable|string|max:255',
            'motivo' => 'nullable|string|max:1000',
            'xml' => 'nullable|string',
            'pdf' => 'nullable|string',
            'erro' => 'nullable|string|max:2000',
            'codigo_erro' => 'nullable|string|max:50',
        ];
    }
}
