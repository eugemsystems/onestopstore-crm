@extends('layouts.simple.master')

@section('title', 'Stale Order Items')

@section('main_content')
    <!-- Breadcrumb starts-->
    <div class="container-fluid">
        <div class="page-title">
            <div class="row">
                <div class="col-6">
                    <h4>Stale Order Items</h4>
                </div>
                <div class="col-6">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">
                                <svg class="stroke-icon">
                                    <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-home') }}"></use>
                                </svg></a></li>
                        <li class="breadcrumb-item active">Stale Order Items</li>

                    </ol>
                </div>
            </div>
        </div>

        <div class="row size-column">
            <livewire:orders.items :stale="true"/>
        </div>
    </div>

@endsection
