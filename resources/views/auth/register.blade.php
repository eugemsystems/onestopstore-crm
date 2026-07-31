@extends('layouts.authentication.master')
@section('title', 'Register')

@section('css')
    <style>
        [x-cloak] { display: none !important; }
        .theme-form { min-height: 600px; }
        dl { display: grid; grid-template-columns: max-content auto; }
        dt { grid-column-start: 1; font-weight: bold; padding-right: 1rem; }
        dd { grid-column-start: 2; margin-bottom: 0.5rem; }

        @media only screen and (max-width: 575.98px) {
            .login-card .login-main {
                /*width: auto;*/
                padding: 20px;
            }
        }
    </style>
@endsection

@section('main_content')
    <!-- login page start-->
    <div class="container-fluid p-0">
        <div class="row m-0">
            <div class="col-12 p-0">
                <div class="login-card login-dark">
                    <div>
                        <div>
                            <a class="logo" href="{{ route('app.dashboard') }}">
                                <img class="img-fluid for-dark" src="{{ asset('logo.png') }}" alt="looginpage">
                                <img class="img-fluid for-light" src="{{ asset('logo.png') }}" alt="looginpage">
                            </a>
                        </div>
                        <div class="login-main">
                            <livewire:pages.auth.register />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
@endsection
