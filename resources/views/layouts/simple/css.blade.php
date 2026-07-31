<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/font-awesome.css') }}">
<!-- ico-font-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/icofont.css') }}">
<!-- Themify icon-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/themify.css') }}">
<!-- Flag icon-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/flag-icon.css') }}">
<!-- Feather icon-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/feather-icon.css') }}">
<!-- Plugins css start-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/slick.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/slick-theme.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/scrollbar.css') }}">
@yield('css')
<!-- Plugins css Ends-->
<!-- Bootstrap css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/bootstrap.css') }}">

<!-- App css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css') }}">
<link id="color" rel="stylesheet" href="{{ asset('assets/css/color-1.css') }}" media="screen">
<!-- Responsive css-->

<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/responsive.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/nestable-style.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/select2.css') }}">
<!-- Toastr css -->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/toastr.min.css')}}">

<style>
    .form-check-input {
        --bs-form-check-bg: var(--bs-body-bg);
        width: 1.6em;
        height: 1.6em;
        margin-top: 0.25em;
        vertical-align: top;
        background-color: var(--bs-form-check-bg);
        background-image: var(--bs-form-check-bg-image);
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
        border: var(--bs-border-width) solid var(--theme-deafult);
        appearance: none;
        print-color-adjust: exact;
    }

    .table th, .table td {
        padding: 0.3rem;
    }

    ul.nav.main-menu.xxx li > .active {
        background-color: rgba(97, 193, 193, 0.74) !important;
        color: #fff !important;
        border-radius: 15px !important;
    }

    .page-link.active, .active > .page-link {
        z-index: 3;
        color: var(--bs-pagination-active-color);
        background-color: var(--bs-info-text-emphasis);
        border-color: var(--bs-info-text-emphasis)
        #055160;
    }

    .page-link {
        position: relative;
        display: block;
        padding: var(--bs-pagination-padding-y) var(--bs-pagination-padding-x);
        font-size: var(--bs-pagination-font-size);
        color: #055160FF;
        text-decoration: none;
        background-color: var(--bs-pagination-bg);
        border: var(--bs-pagination-border-width)
        1px
        solid var(--bs-pagination-border-color);
        transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .table > thead {
        vertical-align: bottom;
        background-color: var(--theme-deafult);

    }
    .table th{
        color: white !important;
    }

    /* Sidebar redesign: clearer section grouping, subtler section titles,
       consistent icon sizing across the mixed sprite-icon / inline-SVG menu items. */
    .page-wrapper.compact-wrapper .page-body-wrapper div.sidebar-wrapper .sidebar-main .sidebar-links .simplebar-wrapper .simplebar-mask .simplebar-content-wrapper .simplebar-content > li.sidebar-main-title {
        margin-top: 22px;
        padding-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
    }
    .page-wrapper.compact-wrapper .page-body-wrapper div.sidebar-wrapper .sidebar-main .sidebar-links .simplebar-wrapper .simplebar-mask .simplebar-content-wrapper .simplebar-content > li.sidebar-main-title:first-child {
        margin-top: 0;
        padding-top: 0;
        border-top: none;
    }
    .page-wrapper .sidebar-main-title h6 {
        opacity: 0.65;
        font-size: 12px;
        letter-spacing: 1px;
    }
    .page-wrapper.compact-wrapper .page-body-wrapper div.sidebar-wrapper .sidebar-main .sidebar-links .simplebar-wrapper .simplebar-mask .simplebar-content-wrapper .simplebar-content > li .sidebar-link svg {
        width: 18px;
        height: 18px;
        vertical-align: middle;
    }
</style>
