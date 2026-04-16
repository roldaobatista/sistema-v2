<?php

namespace App\Http\Requests\UserFavorite;

use Illuminate\Foundation\Http\FormRequest;

class FavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    public function rules(): array
    {
        return [
            'favoritable_type' => 'required|string|in:App\\Models\\WorkOrder',
            'favoritable_id' => 'required|integer',
        ];
    }
}
