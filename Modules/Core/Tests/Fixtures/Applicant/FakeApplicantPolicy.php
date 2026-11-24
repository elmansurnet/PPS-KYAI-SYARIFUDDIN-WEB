<?php

namespace Modules\Core\Tests\Fixtures\Applicant;

use Illuminate\Foundation\Auth\User;

class FakeApplicantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:FakeApplicant');
    }
}
