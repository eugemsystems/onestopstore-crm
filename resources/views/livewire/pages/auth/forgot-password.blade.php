<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

    <form class="theme-form" wire:submit="sendPasswordResetLink">
        <p class="text-center text-xs-center">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </p>
        <!-- Email Address -->
        <x-text-input type="email" id="email" name="email" wire:model="email" label="{{ __('Email Address') }}" autofocus/>

        <div class="row mt-4">
            <x-button class="btn btn-primary-gradien w-100" type="submit" target="sendPasswordResetLink">
                {{ __('Email Password Reset Link') }}
            </x-button>
        </div>


    </form>
</div>
