<?php

namespace App\Http\Requests\Advanced;

use App\Models\Lookups\FollowUpChannel;
use App\Models\Lookups\FollowUpStatus;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFollowUpRequest extends FormRequest
{
    private const CHANNEL_FALLBACK = [
        'phone' => 'Telefone',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'visit' => 'Visita',
    ];

    private const STATUS_FALLBACK = [
        'pending' => 'Pendente',
        'completed' => 'Concluido',
        'overdue' => 'Atrasado',
        'cancelled' => 'Cancelado',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('commercial.followup.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('type') && ! $this->has('channel')) {
            $this->merge(['channel' => $this->input('type')]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);
        $allowedChannels = LookupValueResolver::allowedValues(FollowUpChannel::class, self::CHANNEL_FALLBACK, $tenantId);
        $allowedStatuses = LookupValueResolver::allowedValues(FollowUpStatus::class, self::STATUS_FALLBACK, $tenantId);

        return [
            'followable_type' => 'sometimes|string',
            'followable_id' => 'sometimes|integer|min:1',
            'assigned_to' => ['sometimes', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'scheduled_at' => 'sometimes|date',
            'channel' => ['sometimes', 'string', Rule::in($allowedChannels)],
            'status' => ['sometimes', 'string', Rule::in($allowedStatuses)],
            'notes' => 'nullable|string',
            'result' => 'nullable|string|max:50',
            'completed_at' => 'nullable|date',
        ];
    }
}
