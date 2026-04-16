<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\QuickNote;
use App\Models\User;

class QuickNotesUpdateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, QuickNote $note, User $user, int $tenantId)
    {

        $note->update($data);

        return $note;
    }
}
