<?php

use App\Models\User;
use App\Notifications\PasswordUpdatedNotification;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Hash;


new class extends Component {
    public User $user;
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function updatePassword(): void
    {

        if(Gate::allows('change password')){
            $validated_data = $this->validate([
                'password' => ['required', 'string', 'min:8', 'confirmed', Rules\Password::defaults()],
            ]);

            $validated_data['password'] = Hash::make($validated_data['password']);

            try {
                $this->user->update($validated_data);
                $this->user->notify(new PasswordUpdatedNotification($this->user));
                $this->dispatch('header-refresh')->to('pages.header');
                $this->dispatch('success', 'User password updated successfully');
            } catch (Exception $e) {
                $this->dispatch('error', 'Failed to update password: ' . $e->getMessage());
            }
        }else{
            $this->dispatch('error','You are not authorised to perform this action.');
        }

    }
}; ?>

<div>
    <div class="card mb-0">
        <form class="theme-form">
            <div class="card-header">
                <h4 class="card-title mb-0">Update Password</h4>
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
                        <x-text-input label="{{ __('Password')  }}" name="password" id="password" type="password"
                                      wire:model.defer="password"/>
                    </div>

                    <div class="col-sm-12 col-md-6">
                        <x-text-input type="password" id="password_confirmation" name="password_confirmation"
                                      wire:model="password_confirmation"
                                      label="{{ __('Confirm Password')  }}"/>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <x-button class="btn-primary-gradien" type="button" wire:click="updatePassword" target="updatePassword">Update Password</x-button>
            </div>
        </form>
    </div>
</div>

