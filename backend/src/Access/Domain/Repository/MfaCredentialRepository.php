<?php

namespace App\Access\Domain\Repository;

use App\Access\Domain\Model\MfaCredential;

interface MfaCredentialRepository
{
    public function findForUser(string $userId): ?MfaCredential;

    public function add(MfaCredential $credential): void;

    public function remove(MfaCredential $credential): void;
}
