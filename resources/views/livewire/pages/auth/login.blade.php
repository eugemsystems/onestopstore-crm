<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new class extends Component {
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {

        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        //Cache user data
        getCachedAuthUser();

        $this->redirectIntended(default: route('app.dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <form class="theme-form" wire:submit="login">
        <h4 class="text-center">{{ __('Sign in to account') }} </h4>
        <p class="text-center">{{ __('Enter your email & password to login') }}</p>
        <!-- Email Address -->
        <x-text-input type="email" id="email" name="form.email" wire:model="form.email"
                      label="{{ __('Email Address') }}" autofocus/>
        <!-- Password -->
        <x-text-input type="password" id="password" name="form.password" wire:model="form.password"
                      label="{{ __('Password') }}"/>

        <div class="form-group mb-0" wire:ignore>
            {{-- flex container to split left/right --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check mb-0" wire:ignore>
                    {{-- remember me checkbox --}}
                    <input
                        wire:model="form.remember"
                        class="form-check-input me-2"
                        id="remember"
                        type="checkbox"
                        name="remember"
                    >
                    <label class="form-check-label mb-0" for="remember">
                        {{ __('Remember me') }}
                    </label>
                </div>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-decoration-none small">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>

            <x-button class="btn btn-primary-gradien w-100" type="submit" target="login">
                {{ __('Sign in') }}
            </x-button>

            <div class="d-flex mt-4 justify-content-center">
                <p class="mb-0">{{ __("Don't have an account?") }}</p>
                <a href="{{ route('register') }}" class="text-decoration-none ms-2">{{ __('Create Account') }}</a>
            </div>
    </form>
</div>
