<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketingConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.integration.update');
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|in:rd_station,hubspot,mailchimp,active_campaign',
            'api_key' => 'required|string',
            'sync_contacts' => 'boolean',
            'sync_events' => 'boolean',
        ];
    }
}
