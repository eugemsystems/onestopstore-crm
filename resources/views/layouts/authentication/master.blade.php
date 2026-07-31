<!DOCTYPE html>
<html lang="en">

    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description"
            content="Our business offers a wide range of comprehensive property and development services which cater to the diverse needs of our clients. Our expert team of professionals is committed to providing top-notch solutions that are tailored to the unique requirements of each project. Our portfolio includes a variety of projects, from commercial and residential properties to industrial facilities and infrastructure development.">
        <meta name="keywords"
            content="Civil Engineering, Roads, Structural Engineering, Bio-Digesters, Electrical, Construction & House Plans">
        <meta name="author" content="eugemsystems">
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.png') }}" type="image/x-icon">
        <title>@yield('title') | {{config('app.name')}}</title>
        <!-- Google font-->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300;400;500;600;700;800&amp;display=swap"
            rel="stylesheet">
        @include('layouts.authentication.css')
    </head>

    <body>
        @yield('main_content')
        @include('layouts.authentication.scripts')

        <script>
            'use strict';
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
    </body>

</html>
