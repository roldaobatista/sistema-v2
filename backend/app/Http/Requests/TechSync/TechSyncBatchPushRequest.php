<?php

namespace App\Http\Requests\TechSync;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class TechSyncBatchPushRequest extends FormRequest
{
    private const FORBIDDEN_PAYLOAD_KEYS = [
        'tenant_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    private const COMMON_MUTATION_DATA_KEYS = [
        'id',
        'work_order_id',
        'client_work_order_updated_at',
        'updated_at',
        'latitude',
        'longitude',
    ];

    private const MUTATION_DATA_KEYS = [
        'checklist_response' => ['responses'],
        'expense' => [
            'expense_category_id',
            'affects_technician_cash',
            'affects_net_value',
            'description',
            'amount',
            'expense_date',
            'payment_method',
            'notes',
        ],
        'signature' => ['png_base64', 'captured_at', 'signer_name', 'signer_document'],
        'status_change' => ['to_status', 'status'],
        'displacement_start' => ['type', 'started_at', 'notes'],
        'displacement_arrive' => [],
        'displacement_location' => ['recorded_at'],
        'displacement_stop' => ['stop_id', 'end_latest', 'ended_at'],
        'nps_response' => ['score', 'comment', 'feedback', 'category', 'answered_at', 'responded_at'],
        'complaint' => ['subject', 'description', 'priority'],
        'work_order_create' => ['customer_id', 'title', 'description', 'priority', 'scheduled_date', 'equipment_id'],
        'material_request' => ['urgency', 'priority', 'notes', 'justification', 'warehouse_id', 'items'],
        'feedback' => ['date', 'type', 'message', 'rating'],
        'seal_application' => ['seals', 'equipment_id'],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'mutations' => 'present|array',
            'mutations.*.type' => 'required|string|in:checklist_response,expense,signature,status_change,displacement_start,displacement_arrive,displacement_location,displacement_stop,nps_response,complaint,work_order_create,material_request,feedback,seal_application',
            'mutations.*.data' => 'required|array',
        ];

        // Regras condicionais por tipo de mutation
        foreach ($this->input('mutations', []) as $index => $mutation) {
            $type = $mutation['type'] ?? '';
            $prefix = "mutations.{$index}.data";

            match ($type) {
                'checklist_response' => $rules += [
                    "{$prefix}.work_order_id" => 'required|integer',
                    "{$prefix}.responses" => 'required|array|min:1',
                ],
                'expense' => $rules += [
                    "{$prefix}.work_order_id" => 'sometimes|integer',
                    "{$prefix}.amount" => 'required|numeric|min:0.01',
                    "{$prefix}.description" => 'required|string|max:500',
                ],
                'signature' => $rules += [
                    "{$prefix}.work_order_id" => 'required|integer',
                    "{$prefix}.png_base64" => 'required|string',
                ],
                'status_change' => $rules += [
                    "{$prefix}.work_order_id" => 'required|integer',
                    "{$prefix}.to_status" => 'required_without:'."{$prefix}.status".'|string',
                    "{$prefix}.status" => 'required_without:'."{$prefix}.to_status".'|string',
                ],
                'displacement_start', 'displacement_arrive', 'displacement_location', 'displacement_stop' => $rules += [
                    "{$prefix}.work_order_id" => 'required|integer',
                    "{$prefix}.latitude" => 'sometimes|numeric',
                    "{$prefix}.longitude" => 'sometimes|numeric',
                ],
                'nps_response' => $rules += [
                    "{$prefix}.work_order_id" => 'required|integer',
                    "{$prefix}.score" => 'required|integer|min:0|max:10',
                ],
                'complaint' => $rules += [
                    "{$prefix}.work_order_id" => 'required|integer',
                    "{$prefix}.description" => 'required|string|max:2000',
                ],
                'work_order_create' => $rules += [
                    "{$prefix}.title" => 'sometimes|string|max:255',
                    "{$prefix}.description" => 'sometimes|string|max:2000',
                ],
                'material_request' => $rules += [
                    "{$prefix}.work_order_id" => 'required|integer',
                    "{$prefix}.items" => 'required|array|min:1',
                ],
                'feedback' => $rules += [
                    "{$prefix}.message" => 'required|string|max:2000',
                ],
                'seal_application' => $rules += [
                    "{$prefix}.work_order_id" => 'required|integer',
                    "{$prefix}.seals" => 'required|array|min:1',
                    "{$prefix}.seals.*.seal_number" => 'required|string|max:100',
                ],
                default => null,
            };
        }

        return $rules;
    }

    /**
     * @return array{mutations: array<int, array{type: string, data: array<string, mixed>}>}
     */
    public function sanitizedPayload(): array
    {
        /** @var array<int, array{type: string, data?: array<string, mixed>}> $inputMutations */
        $inputMutations = $this->input('mutations', []);

        $mutations = collect($inputMutations)
            ->map(function (array $mutation): array {
                $type = (string) $mutation['type'];
                $allowedKeys = array_values(array_unique([
                    ...self::COMMON_MUTATION_DATA_KEYS,
                    ...(self::MUTATION_DATA_KEYS[$type] ?? []),
                ]));

                return [
                    'type' => $type,
                    'data' => $this->stripForbiddenKeys(Arr::only($mutation['data'] ?? [], $allowedKeys)),
                ];
            })
            ->values()
            ->all();

        return ['mutations' => $mutations];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function stripForbiddenKeys(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array((string) $key, self::FORBIDDEN_PAYLOAD_KEYS, true)) {
                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->stripForbiddenKeys($value) : $value;
        }

        return $sanitized;
    }
}
