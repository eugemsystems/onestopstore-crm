@extends('layouts.simple.master')

@section('title', $user->initials()." ".$user->last_name)

@section('css')
    <style>
        .document-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
@endsection

@section('main_content')
    <div class="container-fluid basic_table">
        <div class="page-title">
            <div class="row">
                <div class="col-sm-6">
                    <h4>View Registration</h4>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('app.dashboard') }}">
                                <svg class="stroke-icon">
                                    <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-home') }}"></use>
                                </svg>
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('app.dashboard') }}">Registration</a>
                        </li>
                        <li class="breadcrumb-item active">{{$user->initials()." ".$user->last_name}}</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div class="email-wrap bookmark-wrap">
                <div class="row">
                    <div class="col-sm-12 col-xl-3 box-col-6">
                        <div class="md-sidebar">
                            <div class="md-sidebar-aside job-left-aside custom-scrollbar">
                                <div class="email-left-aside">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="email-app-sidebar left-bookmark task-sidebar">
                                                <div class="media">
                                                    <div class="media-size-email">
                                                        @if($user->photo_path)
                                                            <img src="{{ asset('storage/avatars/' . $user->photo_path) }}"
                                                                 class="me-3 rounded-circle" width="80" height="80"
                                                                 alt="Current Avatar">
                                                        @endif
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="f-w-600">{{ $user->first_name." ".$user->last_name }}</h6>
                                                        <p>{{$user->email}}</p>
                                                        <h6>{{unSlugify($user->getRoleNames()->first())}}</h6>

                                                    </div>
                                                </div>
                                                <div class="col-sm-12 mt-4">
                                                    @if($user->account_status !== 'active')
                                                        <form method="POST" action="{{ route('users.activate', $user) }}" class="mt-2">
                                                            @csrf
                                                            <button class="btn btn-success btn-sm w-100">Activate Account</button>
                                                        </form>
                                                    @endif
                                                </div>
                                                <hr>
                                                <ul class="nav main-menu xxx" role="tablist">

                                                    <li>
                                                        <a class="active" id="registration-info-tab" data-bs-toggle="pill"
                                                           href="#registration-info" role="tab" aria-controls="registration-info"
                                                           aria-selected="true"><span class="title">
                                                                <i style="font-size: 24px;" class="ri-registered-line"></i>
                                                                Registration Info </span>
                                                        </a>
                                                    </li>


                                                    <li>
                                                        <hr>
                                                    </li>

                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-xl-9 col-md-12 box-col-12">
                        <div class="email-right-aside bookmark-tabcontent">
                            <div class="cardx email-body radius-left">
                                <div class="ps-0">
                                    <div class="tab-content">
                                        {{--REGISTRATION INFO TAB--}}
                                        <div class="tab-pane fade active show" id="registration-info" role="tabpanel"
                                             aria-labelledby="registration-info-tab">
                                            <div class="card">
                                                <div class="card-body">
                                                    <dl class="row">

                                                        {{-- Personal --}}
                                                        <dt class="col-sm-4">Name</dt>
                                                        <dd class="col-sm-8">{{ $user->first_name }} {{ $user->last_name }}</dd>

                                                        {{-- Contact --}}
                                                        <dt class="col-sm-4">Phone Number</dt>
                                                        <dd class="col-sm-8">{{ $user->phone_number }}</dd>

                                                        <dt class="col-sm-4">Email</dt>
                                                        <dd class="col-sm-8">{{ $user->email }}</dd>
                                                        <hr class="w-75">

                                                    </dl>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
@endsection

