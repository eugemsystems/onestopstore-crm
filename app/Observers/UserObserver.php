<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function created(User $user): void   {
        $this->resetAll($user->id);
    }
    public function updated(User $user): void   { $this->resetAll($user->id); }
    public function deleted(User $user): void   { $this->resetAll($user->id); }
    public function restored(User $user): void  { $this->resetAll($user->id); }
    public function forceDeleted(User $user): void { $this->resetAll($user->id); }

    private function resetAll($userId)
    {
        if($userId === Auth::id()) {
            // If this is the currently logged-in user, reset their cached data
            resetCachedAuthUser();

        }else{
            // If this is not the currently logged-in user, just reset the user data
            resetCachedAuthUser($userId);
        }

        resetAllUsersForSelectInput();

    }

}
