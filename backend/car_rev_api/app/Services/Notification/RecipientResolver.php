<?php

namespace App\Services\Notification;

use App\Models\TrcMailNotiMgr;

/**
 * Maps the approver chosen on a booking to the mailbox configured for them in
 * the notification manager screen.
 */
class RecipientResolver
{
    public function findByEmployeeCode(string $employeeCode): ?TrcMailNotiMgr
    {
        return TrcMailNotiMgr::query()
            ->where('EMPLOYEE_CODE', $employeeCode)
            ->where('ACTIVE_FLAG', 'Y')
            ->orderByDesc('CREATED_AT')
            ->first();
    }

    /**
     * Addresses copied on every notification, typically transport admins.
     *
     * @return list<string>
     */
    public function standingCarbonCopies(): array
    {
        /** @var list<string> $cc */
        $cc = config('notification.cc', []);

        return $cc;
    }
}
