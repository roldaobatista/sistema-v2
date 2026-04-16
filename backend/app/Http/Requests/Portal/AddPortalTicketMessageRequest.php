<?php

namespace App\Http\Requests\Portal;

use App\Models\ClientPortalUser;
use Illuminate\Foundation\Http\FormRequest;

class AddPortalTicketMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth('sanctum')->user();

        return $user instanceof ClientPortalUser && $user->tokenCan('portal:access');
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string',
        ];
    }
}
