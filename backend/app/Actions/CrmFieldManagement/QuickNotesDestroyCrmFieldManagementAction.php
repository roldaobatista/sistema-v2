<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\QuickNote;
use App\Models\User;

class QuickNotesDestroyCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, QuickNote $note, User $user, int $tenantId)
    {

        if ($note->tenant_id !== $tenantId) {
            abort(403);
        }
        $note->delete();

        return null;
    }
}
