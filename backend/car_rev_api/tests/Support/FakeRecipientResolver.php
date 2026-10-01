<?php

namespace Tests\Support;

use App\Models\TrcMailNotiMgr;
use App\Services\Notification\RecipientResolver;

class FakeRecipientResolver extends RecipientResolver
{
    /**
     * @param  array<string, TrcMailNotiMgr>  $managers
     * @param  list<string>  $carbonCopies
     */
    public function __construct(
        private array $managers = [],
        private array $carbonCopies = [],
    ) {}

    public function findByEmployeeCode(string $employeeCode): ?TrcMailNotiMgr
    {
        return $this->managers[$employeeCode] ?? null;
    }

    public function standingCarbonCopies(): array
    {
        return $this->carbonCopies;
    }
}
