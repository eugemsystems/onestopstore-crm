<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <div class="card user-role">
        <div class="card-body border-b-primary border-2">
            <div class="upcoming-box">
                <div class="upcoming-icon bg-primary">
                    <svg class="stroke-icon">
                        <use href="{{ asset('assets/svg/icon-sprite.svg#user-plus') }}"></use>
                    </svg>
                </div>
                <p>User</p>
                <button class="btn btn-primary" type="button" data-bs-toggle="modal"
                        data-bs-target="#add-user" data-whatever="@getbootstrap">{{ __('Add user') }}</button>
            </div>
            <ul class="bubbles role">
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

            <div class="modal fade" id="add-user" tabindex="-1" role="dialog" wire:ignore
                 aria-labelledby="add-role" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-toggle-wrapper social-profile text-start dark-sign-up">
                            <h3 class="modal-header justify-content-center border-0">Create New User</h3>
                            <small class="modal-header justify-content-center border-0 text-center">
                                Default <strong class="text-danger"> password </strong> is the same as the email
                            </small>
                            <div class="modal-body">
                                <livewire:users.components.add-new-user />
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>
