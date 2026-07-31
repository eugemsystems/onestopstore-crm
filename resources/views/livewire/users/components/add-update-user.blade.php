<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public $user;
    public $first_name;
    public $last_name;
    public $email;
    public $phone_number;
    public $update_or_create;

    protected $rules = [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone_number' => 'required|string|max:255',
    ];

    public function mount($user, $update_or_create)
    {
        //dd($update_or_create);
        if ($update_or_create == 'update') {
            $this->user = $user;
            $this->first_name = $user->first_name;
            $this->last_name = $user->last_name;
            $this->email = $user->email;
            $this->phone_number = $user->phone_number;
        }
    }

    public function createProfile()
    {
        if(Gate::allows('create user')){
            $validated_data = $this->validate();
            try {
                $validated_data['password'] = \Illuminate\Support\Facades\Hash::make($this->email);
                User::create($validated_data);

                $this->dispatch('refresh');
                $this->dispatch('success', 'New user created successfully');
            } catch (Exception $e) {
                $this->dispatch('error', 'Failed to create a new user: ' . $e->getMessage());
            }
        }else{
            $this->dispatch('error','You are not authorised to perform this action.');
        }

    }

    public function updateProfile()
    {
        if(Gate::allows('update profile')){
            $data = $this->validate();
            try {
                // 1) Fill the model without saving yet
                $this->user->fill([
                    'first_name' => $this->first_name,
                    'last_name' => $this->last_name,
                    'email' => $this->email,
                    'phone_number' => $this->phone_number,
                ]);

                // 2) Check if the email really changed
                $emailChanged = $this->user->isDirty('email');

                // 3) If so, reset the verified timestamp
                if ($emailChanged) {
                    $this->user->email_verified_at = null;
                }

                // 4) Save everything at once
                $this->user->save();

                // 5) Now that it's saved, send the verification link
                if ($emailChanged) {
                    $this->user->sendEmailVerificationNotification();
                }

                $this->dispatch('refresh');
                $this->dispatch('success', 'Profile details updated successfully');
            } catch (Exception $e) {
                $this->dispatch('error', 'Failed to update profile picture: ' . $e->getMessage());
            }
        }else{
            $this->dispatch('error','You are not authorised to perform this action.');
        }

    }

}; ?>

<div>
    {{dd($this->update_or_create)}}
    <div class="card mb-0">
        <div class="card-header">
            @if($this->update_or_create == 'create')
                <h4 class="card-title mb-0">Create New User</h4>
            @elseif($this->update_or_create == 'update')
                <h4 class="card-title mb-0">Edit User Details</h4>
            @else
                <h4 class="card-title mb-0">Missing mount</h4>
            @endif

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

        </div>
        <div class="card-footer text-end">
            @if($this->update_or_create == 'create')
                <x-button class="btn-primary-gradien" wire:click="createProfile" target="createProfile">Create Profile
                </x-button>
            @elseif($this->update_or_create == 'update')
                <x-button class="btn-primary-gradien" wire:click="updateProfile" target="updateProfile">Update Profile
                </x-button>
            @else
                <h6>NO BUTTON SPECIFIED</h6>
            @endif

        </div>
    </div>
</div>

