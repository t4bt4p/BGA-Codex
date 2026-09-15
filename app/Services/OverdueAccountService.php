<?php

namespace App\Services;

use App\Models\Rental_tb;
use App\Models\User;

class OverdueAccountService
{
    public function suspensionDetails(User $user): array
    {
        $overdue = $this->hasOverdue($user);

        return [
            'code' => 'account_suspended',
            'reason' => $overdue ? 'overdue' : 'disabled',
            'message' => $overdue
                ? 'บัญชีของคุณถูกระงับเนื่องจากบอร์ดเกมเกินกำหนด'
                : 'บัญชีของคุณถูกระงับ กรุณาติดต่อผู้ดูแลระบบ',
        ];
    }

    public function hasOverdue(User $user): bool
    {
        return Rental_tb::where('User_id', $user->User_id)
            ->where('Rental_status', 'active')->where('due_at', '<', now())->exists();
    }

    public function suspendOverdueAccounts(): int
    {
        return User::where('User_status', 1)->whereIn('User_id',
            Rental_tb::select('User_id')->where('Rental_status', 'active')->where('due_at', '<', now())
        )->update(['User_status' => 0]);
    }

    public function suspendIfOverdue(User $user): void
    {
        if ($this->hasOverdue($user)) {
            $user->update(['User_status' => 0]);
        }
    }
}
