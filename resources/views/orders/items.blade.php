@extends('layouts.simple.master')

@section('title', 'Order Items')

@section('main_content')
    <!-- Breadcrumb starts-->
    <div class="container-fluid">
        <div class="page-title">
            <div class="row">
                <div class="col-6">
                    <h4>Order Items</h4>
                </div>
                <div class="col-6">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">
                                <svg class="stroke-icon">
                                    <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-home') }}"></use>
                                </svg></a></li>
                        <li class="breadcrumb-item active">Order Items</li>

                    </ol>
                </div>
            </div>
        </div>

        <div class="row size-column">
            <livewire:orders.items />
        </div>
    </div>

@endsection
