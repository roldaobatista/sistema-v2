<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Customer;
use App\Models\QuickNote;
use App\Models\User;

class QuickNotesStoreCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $note = QuickNote::create([
            ...$data,
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
        ]);

        Customer::where('id', $data['customer_id'])->update(['last_contact_at' => now()]);

        return $note->load(['customer:id,name', 'user:id,name']);
    }
}
