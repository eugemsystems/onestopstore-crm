<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm txt-primary">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form class="theme-form" wire:submit="confirmPassword">
        <!-- Password -->
        <div>
            <x-text-input type="password" id="password" name="password" wire:model="password" label="{{ __('Password') }}"/>
        </div>

        <div class="mt-4">
            <x-button class="btn btn-primary-gradien w-100" type="submit" target="confirmPassword">
                {{ __('Confirm') }}
            </x-button>
        </div>
    </form>
</div>
