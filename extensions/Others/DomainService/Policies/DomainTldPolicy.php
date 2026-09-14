<?php

namespace Paymenter\Extensions\Others\DomainService\Policies;

use App\Models\User;
use App\Policies\BasePolicy;

class DomainTldPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->adminPermission($user, 'admin.domains.view');
    }

    public function view(User $user, $record): bool
    {
        return $this->adminPermission($user, 'admin.domains.view');
    }

    public function create(User $user): bool
    {
        return $this->adminPermission($user, 'admin.domains.manage');
    }

    public function update(User $user, $record): bool
    {
        return $this->adminPermission($user, 'admin.domains.manage');
    }

    public function delete(User $user, $record): bool
    {
        return $this->adminPermission($user, 'admin.domains.manage');
    }

    public function deleteAny(User $user): bool
    {
        return $this->adminPermission($user, 'admin.domains.manage');
    }
}
