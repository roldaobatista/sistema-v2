<?php

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.fine.update');
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,paid,appealed,cancelled',
        ];
    }
}
