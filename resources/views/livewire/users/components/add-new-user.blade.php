<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {

    #[Validate('required|string|max:255')]
    public $first_name;

    #[Validate('required|string|max:255')]
    public $last_name;

    #[Validate('required|string|max:255')]
    public $email;

    #[Validate('required|string|max:255')]
    public $phone_number;

    #[Validate('required|string|max:255')]
    public $role;


    public function createUser()
    {
        if(Gate::allows('create user')){
            $validated_data = $this->validate();
            try {

                $validated_data['password'] = \Illuminate\Support\Facades\Hash::make($this->email);



                $user = User::create($validated_data);
                $user->assignRole($this->role);
                $this->dispatch('success', 'User created successfully');
                $this->dispatch('reload-page');
            } catch (Exception $e) {
                $this->dispatch('error', 'Failed to create a new user: ' . $e->getMessage());
            }
        }else{
            $this->dispatch('error','You are not authorised to perform this action.');
        }

    }
}; ?>

<div>
    <div class="card-header">
        <h4 class="card-title mb-0">Create New User</h4>
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
            <div class="col-sm-12 col-md-6">
                <x-text-input label="First Name" name="first_name" type="text" wire:model.defer="first_name"/>
            </div>

            <div class="col-sm-12 col-md-6">
                <x-text-input label="Last Name" name="last_name" type="text" wire:model.defer="last_name"/>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-sm-12 col-md-6">
                <x-text-input label="Email" name="email" type="email" wire:model.defer="email"/>
            </div>

            <div class="col-sm-12 col-md-6">
                <x-text-input label="Phone Number" name="phone_number" type="text" wire:model.defer="phone_number"/>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-sm-12 col-md-12" wire:ignore>
                <x-select label="Role" :options="allRoleNames()->toArray()" :keyAsValue="true" name="role"
                          id="role" wire:model="role" />
            </div>
        </div>

    </div>
    <div class="card-footer text-end">
        <x-button class="btn-primary-gradien w-100" wire:click="createUser" target="createUser">Create User
        </x-button>
    </div>
</div>

@once
    <script>
        window.addEventListener('reload-page', () => {
            window.location.reload();
        });
    </script>
@endonce
