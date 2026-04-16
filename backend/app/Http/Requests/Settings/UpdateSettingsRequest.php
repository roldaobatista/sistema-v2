<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    private function payload(): array
    {
        return $this->isJson() ? $this->json()->all() : $this->all();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('settings')) {
            return;
        }

        $flatSettings = collect($this->payload())
            ->reject(fn (mixed $value, string|int $key): bool => ! is_string($key) || $key === '' || str_starts_with($key, '_'))
            ->map(fn (mixed $value, string $key): array => [
                'key' => $key,
                'value' => $value,
                'type' => is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : (is_array($value) ? 'json' : 'string')),
                'group' => 'general',
            ])
            ->values()
            ->all();

        if ($flatSettings !== []) {
            $this->merge(['settings' => $flatSettings]);
        }
    }

    public function validationData(): array
    {
        return $this->payload();
    }

    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array|min:1',
            'settings.*.key' => 'required|string|max:100',
            'settings.*.value' => 'nullable',
            'settings.*.type' => 'sometimes|string|in:string,boolean,integer,json',
            'settings.*.group' => 'sometimes|string|max:50',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $settings = $this->input('settings', []);

            if (! is_array($settings) || $settings === []) {
                $validator->errors()->add('settings', 'Informe ao menos uma configuração.');

                return;
            }

            foreach ($settings as $index => $item) {
                if (($item['key'] ?? '') === 'quote_sequence_start') {
                    $start = filter_var($item['value'] ?? null, FILTER_VALIDATE_INT);
                    if ($start === false || (int) $start < 1) {
                        $validator->errors()->add(
                            "settings.{$index}.value",
                            'quote_sequence_start deve ser um inteiro maior ou igual a 1.'
                        );
                    }
                }
            }
        });
    }
}
