<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;

class ReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('receipts.view');
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $user->hasPermission('receipts.view');
    }
}
