<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    public $notifications;

    #[\Livewire\Attributes\On('header-refresh')]
    public function refreshme(){
        $this->notifications = auth()->user()->unreadNotifications;
    }

    public function logout()
    {
        // invoke your action
        (new Logout)();

        // use Volt's redirect helper so Volt knows how to navigate
        $this->redirectRoute(
            name: 'login',       // your named route
            absolute: false,
            navigate: true
        );
    }

    public function mount()
    {
        // load unread notifications for the logged-in user
        $this->notifications = once(function(){
            return auth()->user()->unreadNotifications;
        });
    }

    public function markAsRead($id)
    {
        // mark a single notification as read
        auth()->user()
            ->notifications()
            ->where('id', $id)
            ->update(['read_at' => now()]);

        // reload the list
        $this->mount();
    }

    public function markAllAsRead(){
        $this->notifications->each(function ($notification) {
            $notification->markAsRead();
        });
        // reload the list
        $this->mount();
    }

}; ?>

<div>


    <div class="page-header">
        <div class="header-wrapper row m-0">
            <form class="form-inline search-full col" action="#" method="get">
                <div class="form-group w-100">
                    <div class="Typeahead Typeahead--twitterUsers">
                        <div class="u-posRelative">
                            <input class="demo-input Typeahead-input form-control-plaintext w-100" type="text"
                                   placeholder="Search Riho .." name="q" title="" autofocus>
                            <div class="spinner-border Typeahead-spinner" role="status">
                                <span class="sr-only">Loading... </span>
                            </div>
                            <i class="close-search" data-feather="x"></i>
                        </div>
                        <div class="Typeahead-menu"> </div>
                    </div>
                </div>
            </form>
            <div class="header-logo-wrapper col-auto p-0">
                <div class="logo-wrapper">
                    <a href="{{ route('app.dashboard') }}">
                        <img class="img-fluid for-light"
                             src="{{ asset('assets/images/logo/logo_dark.png') }}" alt="logo-light">
                        <img class="img-fluid for-dark" src="{{ asset('assets/images/logo/logo.png') }}"
                             alt="logo-dark">
                    </a>

                </div>
                <div class="toggle-sidebar">
                    <i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i>
                </div>
            </div>
            <div class="left-header col-xxl-5 col-xl-6 col-lg-5 col-md-4 col-sm-3 p-0">
                <div>
                    <a class="toggle-sidebar" href="#"> <i class="iconly-Category icli"> </i></a>
                    <div class="d-flex align-items-center gap-2 ">
                        <h4 class="f-w-600">{{implode(" ",getWelcomeMessage())}} {{getCachedAuthUser()->first_name}}</h4>
                        <img class="mt-0" src="{{ asset('assets/images/hand.gif') }}" alt="hand-gif">
                    </div>
                </div>
                <div class="welcome-content d-xl-block d-none">
                      <span class="text-truncate col-12">
                          Here’s what’s happening with your account today.
                      </span>
                </div>
            </div>
            <div class="nav-right col-xxl-7 col-xl-6 col-md-7 col-8 pull-right right-header p-0 ms-auto">
                <ul class="nav-menus">
                    {{--Applications dropdown--}}
                    <li class="onhover-dropdown">
                        <svg>
                            <use href="{{ asset('assets/svg/icon-sprite.svg#star') }}"></use>
                        </svg>
                        <div class="onhover-show-div bookmark-flip">
                            <div class="flip-card">
                                <div class="flip-card-inner">
                                    <div class="front">
                                        <h6 class="f-18 mb-0 dropdown-title">Bookmark</h6>
                                        <ul class="bookmark-dropdown">
                                            <li>
                                                <div class="row">
                                                    <div class="col-4 text-center">
                                                        <div class="bookmark-content">
                                                            <div class="bookmark-icon"><i
                                                                    data-feather="file-text"></i></div>
                                                            <span>Forms</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-4 text-center">
                                                        <div class="bookmark-content">
                                                            <div class="bookmark-icon"><i data-feather="user"></i>
                                                            </div><span>Profile</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-4 text-center">
                                                        <div class="bookmark-content">
                                                            <div class="bookmark-icon"><i data-feather="server"></i>
                                                            </div><span>Tables</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="text-center"><a class="flip-btn f-w-700" id="flip-btn"
                                                                       href="javascript:void(0)">Add New Bookmark</a></li>
                                        </ul>
                                    </div>
                                    <div class="back">
                                        <ul>
                                            <li>
                                                <div class="bookmark-dropdown flip-back-content">
                                                    <input type="text" placeholder="search...">
                                                </div>
                                            </li>
                                            <li><a class="f-w-700 d-block flip-back" id="flip-back"
                                                   href="javascript:void(0)">Back</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    {{--Dark mode switch--}}
                    <li>
                        <div class="mode"><i class="moon" data-feather="moon"> </i></div>
                    </li>

                    {{--Notifications dropdown--}}
                    <li class="onhover-dropdown notification-down">
                        <div class="notification-box">
                            <svg>
                                <use href="{{ asset('assets/svg/icon-sprite.svg#notification-header') }}"></use>
                            </svg><span class="badge rounded-pill badge-secondary">{{ $notifications->count() }} </span>
                        </div>
                        <div class="onhover-show-div notification-dropdown">
                            <div class="card mb-0">
                                <div class="card-header">
                                    <div class="common-space">
                                        <h4 class="text-start f-w-600">Notitications</h4>
                                        <div><span>{{ $notifications->count() }} New</span></div>

                                    </div>
                                    <a role="button" wire:click="markAllAsRead">Mark All As Read</a>
                                </div>
                                <div class="card-body">
                                    <div class="notitications-bar">
                                        <div class="notification-card">
                                            <ul>
                                                @forelse($notifications as $notification)
                                                    <li class="sale-history-card">
                                                        <div class="history-pricex">
                                                            <h6 style="text-align: left !important;" class="f-w-500 f-12  mb-0" href="#">{{$notification->data['title']}}</h6>
                                                            <span style="text-align: left !important;" class="mb-0 txt-primary f-w-200 f-10">{{$notification->data['message']}}</span>
                                                        </div>

                                                        <div class="state-time">
                                                            <a role="button" wire:click="markAsRead('{{ $notification->id }}')" class="f-w-500 f-14 f-light mb-0">Mark as read</a>
                                                            <span class="f-w-400 f-14 f-light">{{ $notification->created_at->diffForHumans() }}</span>
                                                        </div>
                                                    </li>
                                                @empty
                                                    <li class="text-center f-12">No new notifications</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    {{--User Profile dropdown--}}
                    <li class="profile-nav onhover-dropdown">
                        <div class="media profile-media">
                            @if(getCachedAuthUser()->photo_path)
                                <img src="{{ asset('storage/avatars/' . getCachedAuthUser()->photo_path) }}"
                                     class="b-r-10" width="50" height="50" alt="Current Avatar">
                            @else
                                <img src="{{ asset('storage/avatars/default.jpg') }}"
                                     class="b-r-10" alt="Current Avatar">
                            @endif

                            <div class="media-body d-xxl-block d-none box-col-none">
                                <div class="d-flex align-items-center gap-2"> <span>{{ ucfirst(getCachedAuthUser()?->first_name) }} </span><i
                                        class="middle fa fa-angle-down"> </i></div>
                                <p class="mb-0 font-roboto">{{ getAuthRole()}}</p>
                            </div>
                        </div>
                        <ul class="profile-dropdown onhover-show-div">
                            <li><a href="{{route('app.profile')}}"><i data-feather="user"></i><span>My Profile</span></a>
                            </li>
                            <li>
                                <a role="button" class="btn btn-pill btn-outline-primary btn-sm" wire:click="logout">Log Out</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
            <script class="result-template" type="text/x-handlebars-template">
                <div class="ProfileCard u-cf">
                    <div class="ProfileCard-avatar"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-airplay m-0"><path d="M5 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1"></path><polygon points="12 15 17 21 7 21 12 15"></polygon></svg></div>
                    <div class="ProfileCard-details">
                        <div class="ProfileCard-realName">name</div>
                    </div>
                </div>
            </script>
            <script class="empty-template" type="text/x-handlebars-template"><div class="EmptyMessage">Your search turned up 0 results. This most likely means the backend is down, yikes!</div></script>
        </div>
    </div>
</div>
