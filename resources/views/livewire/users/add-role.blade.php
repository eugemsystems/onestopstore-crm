<?php

use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;

new class extends Component {

    #[Validate('required|string|max:255')]
    public $role;

    public function addRole()
    {
        if(Gate::allows('create role')){
            $this->validate();

            Role::create(['name' => Str::slug($this->role)]);

            $this->dispatch('success', 'Role added successfully!');

            $this->dispatch('reload-page');
        }else{
            $this->dispatch('error','You are not authorised to perform this action.');
        }

    }
}; ?>

<div>
    <div class="card user-role">
        <div class="card-body border-b-secondary border-2">
            <div class="upcoming-box">
                <div class="upcoming-icon bg-secondary">
                    <svg class="stroke-icon">
                        <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-social') }}"></use>
                    </svg>
                </div>
                <p>Role</p>
                <button class="btn btn-secondary" type="button" data-bs-toggle="modal"
                        data-bs-target="#add-role" data-whatever="@getbootstrap">{{ __('Add role') }}</button>
            </div>
            <ul class="bubbles role role-user">
                <li class="bubble"></li>
                <li class="bubble"></li>
                <li class="bubble"></li>
                <li class="bubble"></li>
                <li class="bubble"></li>
                <li class="bubble"></li>
                <li class="bubble"></li>
                <li class="bubble"></li>
                <li class="bubble"></li>
            </ul>
        </div>
    </div>

    <!--varying modal content-->

    <div class="modal fade" id="add-role" tabindex="-1" role="dialog" wire:ignore
         aria-labelledby="add-role" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-toggle-wrapper social-profile text-start dark-sign-up">
                    <h3 class="modal-header justify-content-center border-0">Add New Role</h3>
                    <div class="modal-body">
                        <div class="col-md-12">
                            <x-text-input id="role" name="role" type="text"
                                          label="Role Name" autofocus wire:model="role"/>
                        </div>
                        <div class="col-md-12">
                            <x-button class="btn btn-primary-gradien" wire:click="addRole" target="addRole">Add New Role
                            </x-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    <script>
        window.addEventListener('reload-page', () => {
            window.location.reload();
        });
    </script>
@endonce
