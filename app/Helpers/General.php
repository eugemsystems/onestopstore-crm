<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

if (!function_exists('getWelcomeMessage')) {
    function getWelcomeMessage(): array
    {
        $user = getCachedAuthUser();
        $name = $user->first_name;
        $hour = date('H');

        if ($hour < 12) {
            $greeting = 'Good morning';
        } elseif ($hour < 18) {
            $greeting = 'Good afternoon';
        } else {
            $greeting = 'Good evening';
        }


        return [$greeting, $name];
    }
}

//Total roles
if (!function_exists('totalRoles')) {
    function totalRoles()
    {
        return Role::count();
    }
}


//Total roles
if (!function_exists('totalPermissions')) {
    function totalPermissions()
    {
        return Permission::count();
    }
}

//All Roles
if (!function_exists('allRoles')) {
    function allRoles(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::all();
    }
}

//All Roles Name
if (!function_exists('allRoleNames')) {
    function allRoleNames(): \Illuminate\Support\Collection
    {
        return Role::all()->pluck('name');
    }
}

//all roles with user count numbers
if (!function_exists('allRolesWithUserCount')) {
    function allRolesWithUserCount(): \Illuminate\Support\Collection
    {
        return Role::withCount('users')
            ->latest()
            ->take(5)
            ->get();
    }
}

//get users per role
if (!function_exists('allUsersPerRole')) {
    function allUsersPerRole($role): \Illuminate\Support\Collection
    {
        return User::whereHas('roles', function ($query) use ($role) {
            $query->where('name', $role);
        })->get();
    }
}

if (!function_exists('allUsersPerRoleForSelectInput')) {
    function allUsersPerRoleForSelectInput($role):Array
    {
        return User::whereHas('roles', function ($query) use ($role) {
            $query->where('name', $role);
        })
            ->select(['id','first_name','last_name'])->get()
            ->mapWithKeys(function($user) {
                return [
                    $user->id => "{$user->first_name} {$user->last_name}"
                ];
            })
            ->toArray();
    }
}

if (!function_exists('allUsersForSelectInput')) {
    function allUsersForSelectInput():Array
    {
        //cache this
        return Cache::remember('all_users_for_select_input',now()->addHour(), function () {
            return User::select(['id','first_name','last_name'])->get()
                ->mapWithKeys(function($user) {
                    return [
                        $user->id => "{$user->first_name} {$user->last_name}"
                    ];
                })
                ->toArray();
        });

    }
}


//all roles with user count numbers
if (!function_exists('allRolesWithPermissionCount')) {
    function allRolesWithPermissionCount(): \Illuminate\Support\Collection
    {
        return Role::withCount('permissions')
            ->latest()
            ->take(5)
            ->get();
    }
}

//all users count
if (!function_exists('totalUsers')) {
    function totalUsers(): int
    {
        return User::count();
    }
}

if (!function_exists('unSlugify')) {
    function unSlugify($slug): string
    {
        return Str::headline($slug);
    }
}

//get all permisios assignmed to a role
if (!function_exists('getAllPermissionsAssignedToRole')) {
    function getAllPermissionsAssignedToRole($role_name): \Illuminate\Support\Collection
    {
        $role = Role::whereName($role_name)->first();
        return $role->permissions->pluck('name');
    }
}

// Payment method label helper
if (!function_exists('paymentMethodLabel')) {
    function paymentMethodLabel($pm): string
    {
        $v = strtolower(trim((string) ($pm ?? '')));
        if ($v === '') return '-';
        return match ($v) {
            'cod' => 'Payment at the office',
            'bank_transfer' => 'Bank Transfer',
            default => strtoupper($v),
        };
    }
}
