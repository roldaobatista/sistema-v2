<?php

namespace App\Http\Requests\SupplierPortal;

use App\Models\PortalGuestLink;
use App\Models\PurchaseQuotation;
use Illuminate\Foundation\Http\FormRequest;

class AnswerSupplierQuotationRequest extends FormRequest
{
    /**
     * Validate that the token route parameter resolves to a valid, non-expired guest link
     * pointing to a PurchaseQuotation that is still pending.
     */
    public function authorize(): bool
    {
        $token = $this->route('token');

        if (empty($token) || ! is_string($token)) {
            return false;
        }

        $guestLink = PortalGuestLink::with('entity')->where('token', $token)->first();

        if (! $guestLink || ! $guestLink->isValid()) {
            return false;
        }

        return $guestLink->entity instanceof PurchaseQuotation;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => 'required|in:submit,reject',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required_if:action,submit|array',
            'items.*.id' => 'required_if:action,submit|integer|exists:purchase_quotation_items,id',
            'items.*.unit_price' => 'required_if:action,submit|numeric|min:0',
        ];
    }
}
