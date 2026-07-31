<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    public User $user;

    #[Validate('required|string|max:255')]
    public $first_name;

    #[Validate('required|string|max:255')]
    public $last_name;

    #[Validate('required|string|max:255')]
    public $email;

    #[Validate('required|string|max:255')]
    public $phone_number;

    public array $event;

    // Telegram fields
    #[Validate('nullable|string|max:255')]
    public $telegram_username;

    #[Validate('nullable|string|max:255')]
    public $telegram_id;


    public function mount($user, $event = [])
    {
        $this->user = $user;
        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
        $this->phone_number = $user->phone_number;
        $this->event = [];
        $this->telegram_username = $user->telegram_username;
        $this->telegram_id = $user->telegram_id;
    }

    public function updateProfile()
    {
        if(Gate::allows('update profile')){
            $this->validate();
            try {

                // 1) Fill the model without saving yet
                $this->user->fill([
                    'first_name' => $this->first_name,
                    'last_name' => $this->last_name,
                    'email' => $this->email,
                    'phone_number' => $this->phone_number,
                    'telegram_username' => $this->telegram_username,
                    'telegram_id' => $this->telegram_id,
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

                if(! empty($this->event)){
                    $this->dispatch($this->event['event']);
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
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Edit User Details</h4>
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
                <div class="col-sm-12 col-md-6">
                    <x-text-input label="Telegram Username" name="telegram_username" type="text" wire:model.defer="telegram_username"/>
                </div>
                <div class="col-sm-12 col-md-6">
                    <x-text-input label="Telegram ID" name="telegram_id" type="text" wire:model.defer="telegram_id"/>
                </div>
            </div>

        </div>
        <div class="card-footer text-end">
            <x-button class="btn-primary-gradien" wire:click="updateProfile" target="updateProfile">Update Profile
            </x-button>
        </div>
    </div>
</div>

