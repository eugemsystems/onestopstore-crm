@extends('layouts.authentication.master')
@section('title', 'Login')

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
                            <!-- Session Status -->
                            <x-auth-session-status class="mb-4" :status="session('status')" />
                            <livewire:pages.auth.login />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        sessionStorage.removeItem('dashboardReloaded');
    </script>
@endsection
