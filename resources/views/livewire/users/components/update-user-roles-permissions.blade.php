<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Permission;


new class extends Component {
    public User $user;
    public string $role = '';
    public array $permissions = [];
    public array $all_permissions = [];
    public array $direct_permissions = [];

    public function mount($user)
    {
        $this->user = $user;
        $this->role = $user->getRoleNames()->first();
        $this->permissions = getAllPermissionsAssignedToRole($this->role)->toArray();
        $this->all_permissions = Permission::all()->pluck('name')->toArray();
        $this->direct_permissions = $user
            ->getDirectPermissions()
            ->pluck('name')
            ->toArray();
    }

    public function updatedRole($value)
    {
        $this->permissions = getAllPermissionsAssignedToRole($value)->toArray();
    }

    public function updateRole(): void
    {
        if(Gate::allows('update role')){
            $this->validate([
                'role' => ['required', 'string', 'exists:roles,name'],
            ]);

            try {

                $this->user->syncRoles($this->role);

                $this->dispatch('refresh');
                $this->dispatch('success', 'User role updated successfully');
            } catch (Exception $e) {
                $this->dispatch('error', 'Failed to update password: ' . $e->getMessage());
            }
        }else{
            $this->dispatch('error','You are not authorised to perform this action.');
        }

    }

    public function addDirectPermission(string $permissionName): void
    {
        if(Gate::allows('create permission')){
            if($this->role == $this->user->getRoleNames()->first()) {
                $this->user->givePermissionTo($permissionName);
                // reload direct perms so the UI stays in sync
                $this->direct_permissions = $this->user
                    ->getDirectPermissions()
                    ->pluck('name')
                    ->toArray();

                $this->dispatch(
                    'success',
                    "Permission “{$permissionName}”  added successfully as a direct permission."
                );
            }else{
                $this->dispatch(
                    'error',
                    "You cannot add a direct permission to a user with a different role selected."
                );
            }
        }else{
            $this->dispatch('error','You are not authorised to perform this action.');
        }

    }

    public function revokeDirectPermission(string $permissionName): void
    {
        if(Gate::allows('update permission')){
            if($this->role == $this->user->getRoleNames()->first()){
                $this->user->revokePermissionTo($permissionName);
                // reload direct perms so the UI stays in sync
                $this->direct_permissions = $this->user
                    ->getDirectPermissions()
                    ->pluck('name')
                    ->toArray();

                $this->dispatch('refresh');
                $this->dispatch(
                    'success',
                    "Permission “{$permissionName}”  revoked successfully as a direct permission."
                );
            }else{
                $this->dispatch(
                    'error',
                    "You cannot revoke a direct permission from a user with a different role selected."
                );
            }
        }else{
            $this->dispatch('error','You are not authorised to perform this action.');
        }

    }
}; ?>

<div>
    <div class="row">
        <div class="col-sm-12 col-md-4">
            <div class="card mb-0">
                <div class="card-header">
                    <h4 class="card-title mb-0">Update Role</h4>
                    <div class="card-options">
                        <a class="card-options-collapse" href="#" data-bs-toggle="card-collapse">
                            <i class="fe fe-chevron-up"></i>
                        </a>
                        <a class="card-options-remove" href="#" data-bs-toggle="card-remove">
                            <i class="fe fe-x"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <x-select label="Roles"
                                  :options="Spatie\Permission\Models\Role::all()->pluck('name')->toArray()"
                                  :keyAsValue="true" name="role"
                                  id="role" wire:model.live="role" :value="$this->role"/>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <x-button class="btn-primary-gradien w-100" wire:click="updateRole" target="updateRole">Update User
                        Role
                    </x-button>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{Str::headline($this->role)}} - Assigned Permissions</h4>
                    <div class="card-options">
                        <a class="card-options-collapse" href="#" data-bs-toggle="card-collapse">
                            <i class="fe fe-chevron-up"></i>
                        </a>
                        <a class="card-options-remove" href="#" data-bs-toggle="card-remove">
                            <i class="fe fe-x"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-check-size rtl-input">
                        <div class="row">
                            @foreach($all_permissions as $permission)
                                <div class="col-6 col-md-3 mb-2">
                                    @if(in_array($permission, $this->permissions) || in_array($permission, $this->direct_permissions))
                                        <label class="d-block txt-success" for="{{$permission}}">
                                            <input type="checkbox" name="permissions[]" id="{{$permission}}"
                                                   value="{{$permission}}" class="checkbox_animated" checked disabled
                                            > {{$permission}}
                                        </label>
                                    @else
                                        <label class="d-block" for="{{$permission}}">
                                            <input type="checkbox" name="permissions[]" id="{{$permission}}"
                                                   value="{{$permission}}" class="checkbox_animated"
                                                   wire:change="addDirectPermission('{{ $permission }}')"
                                            > {{$permission}}
                                        </label>
                                    @endif

                                </div>
                            @endforeach
                        </div>

                        <hr/>
                        <h6>Direct user Permissions</h6>
                        <div class="row">
                            @if($direct_permissions)
                                @foreach($direct_permissions as $permission)
                                    <div class="col-6 col-md-3 mb-2">
                                        <label class="d-block" for="{{$permission}}">
                                            <input type="checkbox" name="permissions[]" id="{{$permission}}"
                                                   value="{{$permission}}" class="checkbox_animated" checked
                                                   wire:change="revokeDirectPermission('{{ $permission }}')"
                                            > {{$permission}}
                                        </label>
                                    </div>
                                @endforeach
                            @else
                                <h5 class="text-center text-danger">No direct permissions assigned</h5>
                            @endif

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
