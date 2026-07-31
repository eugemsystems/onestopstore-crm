@extends('layouts.simple.master')

@section('title', $user->initials()." ".$user->last_name)

@section('css')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/intltelinput.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/tagify.css') }}">
@endsection

@section('main_content')
    <div class="container-fluid basic_table">
        <div class="page-title">
            <div class="row">
                <div class="col-sm-6">
                    <h4>Edit User</h4>
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
                            <a href="{{ route('app.dashboard') }}">Users</a>
                        </li>
                        <li class="breadcrumb-item active">{{$user->initials()." ".$user->last_name}}</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div class="email-wrap bookmark-wrap">
                <div class="row">
                    <div class="col-xl-3 box-col-6">
                        <div class="md-sidebar">
                            <a class="btn btn-primary md-sidebar-toggle" href="javascript:void(0)">filter</a>
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
                                                <ul class="nav main-menu xxx" role="tablist">
                                                    <li class="nav-item">
                                                        <button class="badge-primary btn-block btn-mail w-100" type="button"
                                                                data-bs-toggle="modal" data-bs-target="#exampleModal"><i
                                                                class="me-2" data-feather="check-circle"></i> Create New User
                                                        </button>
                                                    </li>
                                                    <li class="nav-item">
                                                        <span class="main-title">Actions</span>
                                                    </li>
                                                    <li>
                                                        <a class="active" id="profile-photo-tab" data-bs-toggle="pill"
                                                           href="#profile-photo" role="tab" aria-controls="profile-photo"
                                                           aria-selected="true"><span class="title">
                                                                <i style="font-size: 24px;" class="icofont icofont-world"></i>
                                                                Profile Photo </span>
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <a class="show" id="edit-profile-tab" data-bs-toggle="pill"
                                                           href="#edit-profile" role="tab" aria-controls="edit-profile"
                                                           aria-selected="true"><span class="title">
                                                                <i style="font-size: 24px;" class="icofont icofont-ui-user"></i>
                                                                Edit Profile </span>
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <a class="show" id="change-password-tab" data-bs-toggle="pill"
                                                           href="#change-password" role="tab" aria-controls="change-password"
                                                           aria-selected="true"><span class="title">
                                                                <i style="font-size: 24px;" class="icofont icofont-unlock"></i>
                                                                Change Password </span>
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <a class="show" id="roles-permissions-tab" data-bs-toggle="pill"
                                                           href="#roles-permissions" role="tab" aria-controls="roles-permissions"
                                                           aria-selected="true"><span class="title">
                                                                <i style="font-size: 24px;" class="icofont icofont-shield"></i>
                                                                Roles & Permissions </span>
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
                    <div class="col-xl-9 col-md-12 box-col-12">
                        <div class="email-right-aside bookmark-tabcontent">
                            <div class="cardx email-body radius-left">
                                <div class="ps-0">
                                    <div class="tab-content">
                                        <div class="tab-pane fade active show" id="profile-photo" role="tabpanel"
                                             aria-labelledby="profile-photo-tab">
                                            <livewire:users.components.upload-avatar :user="$user" />
                                        </div>

                                        <div class="tab-pane fade show" id="edit-profile" role="tabpanel"
                                             aria-labelledby="edit-profile-tab">
                                            <livewire:users.components.update-user-details :user="$user" />
                                        </div>

                                        <div class="tab-pane fade show" id="change-password" role="tabpanel"
                                             aria-labelledby="change-password-tab">
                                            <div class="login-main" >
                                                <livewire:users.components.update-password :user="$user" />
                                            </div>
                                        </div>

                                        <div class="tab-pane fade show" id="roles-permissions" role="tabpanel"
                                             aria-labelledby="roles-permissions-tab">
                                            <livewire:users.components.update-user-roles-permissions :user="$user" />
                                        </div>

                                        <div class="modal fade modal-bookmark" id="createtag" tabindex="-1" role="dialog"
                                             aria-hidden="true">
                                            <div class="modal-dialog modal-lg" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">Create Tag</h4>
                                                        <button class="btn-close" type="button" data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form class="form-bookmark needs-validation" novalidate="">
                                                            <div class="row">
                                                                <div class="mb-3 mt-0 col-md-12">
                                                                    <label>Tag Name</label>
                                                                    <input class="form-control" type="text" required=""
                                                                           autocomplete="off">
                                                                </div>
                                                                <div class="mt-0 col-md-12">
                                                                    <label>Tag color </label>
                                                                    <input class="form-color d-block" type="color"
                                                                           value="#006666">
                                                                </div>
                                                            </div>
                                                            <button class="btn btn-secondary" type="button">Save</button>
                                                            <button class="btn btn-primary" type="button"
                                                                    data-bs-dismiss="modal">Cancel</button>
                                                        </form>
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
    </div>
@endsection
