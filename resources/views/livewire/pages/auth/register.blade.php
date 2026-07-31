<?php

use App\Enums\AccountStatusEnums;
use App\Models\User;
use App\Models\Document;
use App\Notifications\NewUserRegistered;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component {
    use WithFileUploads;

    #[Validate('required|string')]
    public string $first_name = '';

    #[Validate('required|string')]
    public string $last_name = '';

    #[Validate('required|string|max:20|unique:' . User::class . ',phone_number')]
    public string $phone_number = '';

    #[Validate('required|string|lowercase|unique:' . User::class . ',email')]
    public string $email = '';

    #[Validate('required|string|confirmed')]
    public string $password = '';

    #[Validate('required|string|')]
    public string $password_confirmation = '';


    public function register(): void
    {
        $data = $this->validate();

        try {
            $data['uuid'] = Str::uuid();
            $data['password'] = Hash::make($data['password']);
            $data['photo_path'] = 'default.png';

            $user = User::create($data);
            $user->assignRole('admin');

            // ensure enum exists on this model and set the first status
            $user->forceFill(['account_status' => AccountStatusEnums::pending->name,])->save();

            // fire the standard event for any other listeners you may add later
            event(new Registered($user));

            // email admins (collect from Settings; fall back to env ADMIN_EMAIL)
            $emails = collect([
                getCachedSetting('app_contact_email'),
            ])->filter()->unique()->values()->all();

            if (!empty($emails)) {
                Notification::route('mail', $emails)->notify(new NewUserRegistered($user));
            }

            $this->dispatch('success', 'Registration complete.');
            $this->reset();
            $this->step = 1;

            // log in and send to activation notice page
            Auth::login($user);

            $this->redirect('/activation-pending');
        } Catch (\Exception $e) {
            $this->dispatch('error', 'Registration failed: ' . $e->getMessage());
        }
    }

};

// ——————————————————————————————————————————
// Volt view below — Do NOT alter formatting
// ——————————————————————————————————————————
?>
<div>


    <div class="p-1">
        <div class="text-center mb-4">
            <h5>REGISTRATION</h5>
        </div>

        <hr>

        <!-- Step 1: Personal Details -->
        <div class="space-y-4" x-cloak>
            <h5 class="pt-0 mb-3">Personal Details</h5>
            <div class="row mb-3">
                <div class="col-sm-6">
                    <x-text-input wire:model="first_name" name="first_name" label="First Name" required/>
                </div>
                <div class="col-sm-6">
                    <x-text-input wire:model="last_name" name="last_name" label="Last Name" required/>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <x-text-input wire:model="phone_number" name="phone_number" label="Cell Number" required/>
                </div>
                <div class="col-sm-6">
                    <x-text-input wire:model="email" name="email" label="Email" required/>
                </div>

            </div>

        </div>
        <!-- Step 5: Account & Declaration -->
        <div class="space-y-4" x-cloak>
            <h5 class="pt-0 mb-3">Account Details</h5>
            <div class="row mb-3">
                <div class="col-sm-6">
                    <x-text-input type="password" wire:model="password" name="password" label="Password" required/>
                </div>
                <div class="col-sm-6">
                    <x-text-input type="password" wire:model="password_confirmation" name="password_confirmation"
                                  label="Confirm Password" required/>
                </div>
            </div>

        </div>
        <!-- Navigation Buttons with Loaders -->
        <div class="absolute bottom-4 w-full flex justify-between px-1" x-cloak>
            <hr class="w-100">

            <div class="d-flex mt-4 justify-content-center">

                <x-button target="register"
                          class="btn btn-primary-gradien w-100 text-decoration-none ms-2">{{ __('Login') }}</x-button>
                @if(auth()->check())
                    &nbsp;|
                    <a href="{{ route('app.dashboard') }}"
                       class="text-decoration-none ms-2">{{ __('Dashboard') }}</a>
                @endif
            </div>

            @if ($errors->any() && $step === 8)
                <div class="alert alert-danger rounded-2 mb-4">
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>

    </div>
</div>
