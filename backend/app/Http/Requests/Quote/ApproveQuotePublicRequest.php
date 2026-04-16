<?php

namespace App\Http\Requests\Quote;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;

class ApproveQuotePublicRequest extends FormRequest
{
    /**
     * Validate that the magic_token route parameter resolves to a valid, sent quote.
     */
    public function authorize(): bool
    {
        $magicToken = $this->route('magicToken');

        if (empty($magicToken) || ! is_string($magicToken)) {
            return false;
        }

        return Quote::where('magic_token', $magicToken)
            ->where('status', Quote::STATUS_SENT)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'accept_terms' => 'required|accepted',
        ];
    }
}
