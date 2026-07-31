<?php

namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): void
    {
        $cache_key = 'auth_user_' . Auth::id();
        Cache::forget($cache_key);

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();
    }
}
