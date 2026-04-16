<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmDeal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealsUpdateStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        /** @var CrmDeal $deal */
        $deal = $this->route('deal');

        return [
            'stage_id' => ['required', Rule::exists('crm_pipeline_stages', 'id')->where('pipeline_id', $deal->pipeline_id)],
        ];
    }
}
