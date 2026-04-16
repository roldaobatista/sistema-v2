<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class CreateWebhookRequest extends FormRequest
{
    protected array $eventAliases = [
        'nota.autorizada' => 'authorized',
        'nota.cancelada' => 'cancelled',
        'nota.rejeitada' => 'rejected',
        'nota.processando' => 'processing',
        'nota.corrigida' => 'corrected',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('events') && $this->input('events') === '') {
            $this->merge(['events' => []]);
        }

        if (! $this->has('events') || ! is_array($this->input('events'))) {
            return;
        }

        $events = collect($this->input('events'))
            ->map(function ($event) {
                $normalized = strtolower(trim((string) $event));

                return $this->eventAliases[$normalized] ?? $normalized;
            })
            ->filter(fn (string $event) => $event !== '')
            ->values()
            ->all();

        $this->merge(['events' => $events]);
    }

    public function rules(): array
    {
        return [
            'url' => 'required|url|max:500',
            'events' => 'nullable|array',
            'events.*' => 'in:authorized,cancelled,rejected,processing,corrected',
        ];
    }
}
