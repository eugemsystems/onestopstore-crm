<!DOCTYPE html>
<html lang="en" @if (Route::currentRouteName() == 'app.rtl_layout') dir="rtl" @endif>

<head>
    @include('layouts.simple.head')
    @include('layouts.simple.css')
    <!-- Vendor Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        [x-cloak] { display: none !important; }
        .text-primary {
            color: #24447c !important;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet"/>
</head>
    <body>
        <!-- loader starts-->
        <div class="loader-wrapper">
            <div class="loader">
                <div class="loader4"></div>
            </div>
        </div>
        <!-- loader ends-->

        <!-- tap on top starts-->
        <div class="tap-top"><i data-feather="chevrons-up"></i></div>
        <!-- tap on tap ends-->

        <!-- page-wrapper Start-->
        <div class="page-wrapper compact-wrapper" id="pageWrapper">

            <!-- Page header start -->
            {{--@include('layouts.simple.header')--}}
            <livewire:pages.header />
            <!-- Page header end-->

            <!-- Page Body Start-->
            <div class="page-body-wrapper">

                <!-- Page sidebar start-->
                @include('layouts.simple.sidebar')


                <div class="page-body">

                    @yield('main_content')
                </div>

                <!-- footer start-->
                @include('layouts.simple.footer')
                <!-- footer end-->
            </div>
            <!-- page-wrapper Ends-->
        </div>

        @stack('modals')

        {{-- scripts --}}
        @include('layouts.simple.script')

        @include('layouts.alerts')
        <script>
            'use strict';
            (function () {
                const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                    return new bootstrap.Popover(popoverTriggerEl);
                });
            })();
            document.addEventListener('livewire:init', () => {
                Livewire.on('success', (message, title) => {
                    if (typeof title === 'undefined') {
                        title = 'Success!';
                    }
                    Swal.fire({
                        title: title,
                        text: message,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-primary waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                });

                Livewire.on('error', (message, title) => {
                    if (typeof title === 'undefined') {
                        title = 'Error Occurred!';
                    }

                    Swal.fire({
                        title: title,
                        text: message,
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                });

                Livewire.on('warning', (message, title) => {
                    if (typeof title === 'undefined') {
                        title = 'Warning';
                    }
                    Swal.fire({
                        title: title,
                        text: message,
                        icon: 'warning',
                        customClass: {
                            confirmButton: 'btn btn-warning waves-effect waves-light'
                        },
                        buttonsStyling: false
                    });
                });

                Livewire.on('custom', (message, icon, confirmButtonText) => {
                    if (typeof icon === 'undefined') {
                        icon = 'success';
                    }
                    if (typeof confirmButtonText === 'undefined') {
                        confirmButtonText = 'Ok, got it!';
                    }
                    Swal.fire({
                        text: message,
                        icon: icon,
                        buttonsStyling: false,
                        confirmButtonText: confirmButtonText,
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                });

                Livewire.on('confirm', (data) => {
                    // If data is wrapped in an array-like structure, access the first element
                    const actualData = Array.isArray(data) ? data[0] : data;

                    // Destructure the actual data object with defaults as fallbacks
                    const {
                        message = 'Default message',
                        title = 'Are you sure?',
                        icon = 'warning',
                        confirmButtonText = 'Yes, delete it!',
                        cancelButtonText = 'Cancel',
                        event = null
                    } = actualData;


                    Swal.fire({
                        title: title,
                        text: message,
                        icon: icon,
                        showCancelButton: true,
                        confirmButtonText: confirmButtonText,
                        cancelButtonText: cancelButtonText,
                        customClass: {
                            confirmButton: 'btn btn-danger me-3 waves-effect waves-light',
                            cancelButton: 'btn btn-outline-secondary waves-effect',
                            text: 'text-danger'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Emit the Livewire event to execute the action
                            Livewire.dispatch(event);
                            // Listen for operation response
                            Livewire.on('operation-response', ({ status, message }) => {
                                Swal.fire({
                                    icon: status === 'success' ? 'success' : 'error',
                                    title: status === 'success' ? 'Success!' : 'Error!',
                                    text: message,
                                    customClass: {
                                        confirmButton: status === 'success' ? 'btn btn-success waves-effect' : 'btn btn-danger waves-effect'
                                    },
                                    buttonsStyling: false
                                });
                            });
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            Swal.fire({
                                title: 'Cancelled',
                                text: 'Operation cancelled',
                                icon: 'error',
                                customClass: {
                                    confirmButton: 'btn btn-success waves-effect'
                                },
                                buttonsStyling: false
                            });
                        }
                    });
                });

            });
        </script>

        @stack('scripts')

        <script>
            function initSidebarMenu() {
                if (!$('#pageWrapper').hasClass('compact-wrapper')) return;

                // Remove duplicate arrows (sidebar-menu.js may have already appended them)
                $('.sidebar-title .according-menu').remove();

                // Append fresh toggle arrows
                $('.sidebar-title').append('<div class="according-menu"><i class="fa fa-angle-right"></i></div>');

                // Hide all submenus first, then reveal active ones
                $('.sidebar-submenu, .menu-content').hide();

                // Active page highlight — open the submenu of the current page
                var current = window.location.pathname;
                var found = false;
                $('.sidebar-wrapper nav ul li a').each(function () {
                    var link = $(this).attr('href');
                    if (!link) return;
                    try {
                        var linkPath = new URL(link, window.location.origin).pathname;
                        if (!found && (current === linkPath || (linkPath.length > 1 && current.startsWith(linkPath)))) {
                            found = true;
                            $(this).addClass('active');
                            // Show the parent submenu
                            var $submenu = $(this).closest('ul.sidebar-submenu, ul.menu-content');
                            $submenu.show();
                            // Mark the parent title as active and set down arrow
                            var $titleLink = $submenu.prev('a.sidebar-title');
                            $titleLink.addClass('active');
                            $titleLink.find('.according-menu').html('<i class="fa fa-angle-down"></i>');
                        }
                    } catch (e) {}
                });

                // CRITICAL: remove ALL click handlers (including sidebar-menu.js unnamespaced one)
                // then add a single clean handler — prevents the open-then-instantly-close bug
                $('.sidebar-title').off('click').on('click', function () {
                    var $this = $(this);
                    var $next = $this.next('.sidebar-submenu, .menu-content');
                    var isHidden = $next.is(':hidden');

                    // Collapse everything
                    $('.sidebar-title').removeClass('active').find('.according-menu').html('<i class="fa fa-angle-right"></i>');
                    $('.sidebar-submenu, .menu-content').slideUp('normal');

                    // If it was closed, open it now
                    if (isHidden) {
                        $this.addClass('active');
                        $this.find('.according-menu').html('<i class="fa fa-angle-down"></i>');
                        $next.slideDown('normal');
                    }
                });
            }

            // On full page load (F5 / direct navigation) wait for scripts to settle
            document.addEventListener('DOMContentLoaded', function () {
                // sidebar-menu.js runs synchronously and hides everything — we override after it's done
                setTimeout(initSidebarMenu, 0);
            });

            // On Livewire hydration (login redirect, SPA nav) use a longer delay
            // to let Livewire fully settle before we re-init
            document.addEventListener('livewire:init', function () {
                setTimeout(initSidebarMenu, 200);
            });

            document.addEventListener('livewire:navigated', function () {
                setTimeout(initSidebarMenu, 200);
            });
        </script>


    </body>

</html>
