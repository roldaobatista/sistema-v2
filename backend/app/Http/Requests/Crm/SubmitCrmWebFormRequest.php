<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmWebForm;
use Illuminate\Foundation\Http\FormRequest;

class SubmitCrmWebFormRequest extends FormRequest
{
    /**
     * Validate that the form slug resolves to an active web form,
     * and reject obvious bot submissions via honeypot field.
     * Rate limiting is enforced at route level (throttle:30,1).
     */
    public function authorize(): bool
    {
        // Honeypot: if the hidden field is filled, it's a bot
        if ($this->filled('website_url')) {
            return false;
        }

        $slug = $this->route('slug');

        if (empty($slug) || ! is_string($slug)) {
            return false;
        }

        return CrmWebForm::where('slug', $slug)->active()->exists();
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'nome' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:5000'],
            'mensagem' => ['nullable', 'string', 'max:5000'],
            'company' => ['nullable', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            // Honeypot field — must remain empty (bots auto-fill it)
            'website_url' => ['nullable', 'max:0'],
        ];
    }
}
