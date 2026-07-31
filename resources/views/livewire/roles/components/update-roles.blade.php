<?php

use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

new class extends Component {
    public array $roles = [];
    public array $permissions = [];
    public array $rolePermissions = [];
    public ?string $openRole = null;

    public function mount()
    {
        $this->roles = Role::all()->toArray();
        $this->permissions = Permission::all()->toArray();

        foreach (Role::all() as $role) {
            $this->rolePermissions[$role->name] = $role
                ->permissions
                ->pluck('name')
                ->toArray();
        }

        // default: first role open
        $this->openRole = $this->roles[0]['name'] ?? null;
    }

    public function setOpen(string $roleName): void
    {
        $this->openRole = $roleName;
    }

    public function togglePermission(string $roleName, string $permName, bool $granted)
    {
        if(Gate::allows('update role')) {
            $role = Role::findByName($roleName);

            if ($granted) {
                $role->givePermissionTo($permName);
                $this->rolePermissions[$roleName][] = $permName;
                app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
            } else {
                $role->revokePermissionTo($permName);
                $this->rolePermissions[$roleName] = array_filter(
                    $this->rolePermissions[$roleName],
                    fn($p) => $p !== $permName
                );
                app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }else{
            $this->dispatch('error', 'You are not authorised to perform this action');
        }

    }
};
?>

<div>
    <div class="accordion dark-accordion" id="simpleaccordion">
        @foreach($roles as $r)
            @php
                $roleName = $r['name'];
                $heading  = 'heading-' . $roleName;
                $collapse = 'collapse-' . $roleName;
                $has      = $rolePermissions[$roleName] ?? [];
                $active   = $openRole === $roleName;
            @endphp

            <div class="accordion-item">
                <h2 class="accordion-header" id="{{ $heading }}">
                    <button
                        class="accordion-button bg-light-primary txt-primary active {{ $active ? '' : 'collapsed' }}"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $collapse }}"
                        aria-expanded="{{ $active ? 'true' : 'false' }}"
                        aria-controls="{{ $collapse }}"
                        wire:click="setOpen('{{ $roleName }}')"
                    >
                        <strong>{{ Str::headline($roleName) }}</strong>
                        <i class="svg-color" data-feather="chevron-down"></i>
                    </button>
                </h2>

                <div
                    id="{{ $collapse }}"
                    class="accordion-collapse collapse {{ $active ? 'show' : '' }}"
                    aria-labelledby="{{ $heading }}"
                    data-bs-parent="#simpleaccordion"
                >
                    <div class="accordion-body">
                        <div class="row">
                            @foreach($permissions as $p)
                                @php($permName = $p['name'])
                                <div class="col-6 col-md-3 mb-2">
                                    <label class="d-block" for="{{ $roleName }}-{{ $permName }}">
                                        <input
                                            type="checkbox"
                                            id="{{ $roleName }}-{{ $permName }}"
                                            value="{{ $permName }}"
                                            class="checkbox_animated"
                                            @checked(in_array($permName, $has))
                                            wire:change="togglePermission('{{ $roleName }}','{{ $permName }}',$event.target.checked)"
                                        >
                                        {{ $permName }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
