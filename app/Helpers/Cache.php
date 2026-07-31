<?php

use App\Enums\TransactionPurposeEnums;
use App\Enums\TransactionStatusEnums;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

if (!function_exists('getCachedAuthUser')) {
    function getCachedAuthUser(): mixed
    {
        $cache_key = 'auth_user_'.Auth::id();
        return Cache::remember($cache_key,now()->addHour(), function () {
            return User::select([
                'id','uuid','first_name', 'last_name',
                'phone_number', 'email',
                'photo_path','account_status'
            ])->find(Auth::id());
        });
    }
}

if (!function_exists('resetCachedAuthUser')) {
    function resetCachedAuthUser($userId = null)
    {
        $userId = $userId ?: Auth::id();
        $cache_key = 'auth_user_' . $userId;
        Cache::forget($cache_key);

        // Only pre-cache if THIS is the currently logged-in user
        if (Auth::check() && $userId == Auth::id()) {
            getCachedAuthUser();
        }
    }
}

if (!function_exists('getAuthRoles')) {
    function getAuthRoles(): mixed
    {
        return getCachedAuthUser()?->roles;
    }
}

if (!function_exists('getAuthRole')) {
    function getAuthRole(): mixed
    {
        return Str::headline(getCachedAuthUser()?->roles[0]->name);
    }
}

//reset all_users_for_select_input
if (!function_exists('resetAllUsersForSelectInput')) {
    function resetAllUsersForSelectInput()
    {
        Cache::forget('all_users_for_select_input');
        //cache the data again
        allUsersForSelectInput();
    }
}

//updateCachedSettings
if (!function_exists('updateCachedSettings')) {
    function updateCachedSettings()
    {
        Cache::forget('settings');
        //cache the data again
        cachedSettings();
    }
}

//getCachedSettings
if (!function_exists('cachedSettings')) {
    function cachedSettings()
    {
        return Cache::rememberForever('settings', function () {
            return \App\Models\Setting::all()->pluck('value', 'key');
        });
    }
}

//getCachedSetting
if (!function_exists('getCachedSetting')) {
    function getCachedSetting(string $key)
    {
        return cachedSettings()[$key] ?? null;
    }
}

//updateCachedOrderStatuses
if (!function_exists('updateCachedOrderStatuses')) {
    function updateCachedOrderStatuses()
    {
        Cache::forget('order-statuses');
        //cache the data again
        cachedOrderStatuses();
    }
}

//updateCachedOrderStatuses
if (!function_exists('cachedOrderStatuses')) {
    function cachedOrderStatuses()
    {
        return Cache::rememberForever('order-statuses', function () {
            return \App\Models\OrderStatus::all()->pluck('name', 'id');
        });
    }
}


