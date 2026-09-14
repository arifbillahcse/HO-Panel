<?php

namespace App\Policies;

use App\Models\User;

class BasePolicy
{
    protected function adminPermission(User $user, $permission): bool
    {
        // Only do this if the request is under /admin. "admin/*" alone does not
        // match the bare "/admin" path (the dashboard itself has nothing after
        // the slash), which used to hide extension nav groups only there.
        return (request()->is('admin') || request()->is('admin/*') || request()->routeIs('paymenter.livewire.update')) && $user->hasPermission($permission);
    }
}
