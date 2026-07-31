@extends('layouts.simple.master')

@section('title', 'Dashboard')

@section('css')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/animate.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/date-picker.css') }}">
    <style>
        .nav-link {
            font-size: var(--bs-nav-link-font-size);
            font-weight: var(--bs-nav-link-font-weight);
            color: #807 !important;
        }

        /* Improved contact cards */
        .contact-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .contact-card:hover {
            transform: translateY(-3px);
        }
        .contact-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .contact-value {
            word-break: break-all;
            font-size: 0.85rem;
        }

        /* Stand summary cards */
        .stand-summary-card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .stand-summary-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stand-header {
            border-bottom: 1px solid #eee;
            padding: 1rem;
            background-color: #f9f9f9;
            border-radius: 8px 8px 0 0;
        }
        .stand-body {
            padding: 1rem;
        }
        .stand-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #f0f0f0;
        }
        .stand-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .stand-detail-label {
            font-weight: 500;
            color: #666;
        }
        .stand-detail-value {
            font-weight: 600;
            text-align: right;
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        @media (max-width: 767px) {
            /* Mobile optimizations */
            .contact-card .project-details {
                flex-direction: column;
                align-items: flex-start;
            }
            .contact-card .project-counter {
                margin-bottom: 0.5rem;
            }
            .stand-summary-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
@endsection

@section('main_content')
    <!-- Breadcrumb starts-->
    <div class="container-fluid">
        <div class="page-title">
            <div class="row">
                <div class="col-6">
                    <h4>Dashboard</h4>
                </div>
                <div class="col-6">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">
                                <svg class="stroke-icon">
                                    <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-home') }}"></use>
                                </svg></a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="row size-column">
            {{--Left Section--}}
            <div class="col-md-8 box-col-12">
                {{-- Stats & Status Cards --}}
                @php
                    use App\Models\Order;
                    use App\Models\OrderProduct;

                    $ordersTotal = Order::count();
                    $orderItemsTotal = OrderProduct::count();

                    // Orders by status (canonicalize using slug/id and merge synonyms)
                    $ordersRaw = Order::query()
                        ->leftJoin('order_statuses','order_statuses.id','=','orders.order_status_id')
                        ->selectRaw("\n                            LOWER(TRIM(COALESCE(NULLIF(order_statuses.slug,''), NULLIF(orders.order_status,''), '--'))) as key_slug,\n                            MIN(COALESCE(NULLIF(order_statuses.name,''), COALESCE(NULLIF(order_statuses.slug,''), NULLIF(orders.order_status,''), '--'))) as raw_name,\n                            COUNT(*) as cnt\n                        ")
                        ->groupByRaw("LOWER(TRIM(COALESCE(NULLIF(order_statuses.slug,''), NULLIF(orders.order_status,''), '--')))")
                        ->orderBy('key_slug')
                        ->get();

                    $synonyms = [
                        'canceled' => 'cancelled',
                    ];

                    $ordersByStatus = [];
                    foreach ($ordersRaw as $row) {
                        $key = $row->key_slug ?: '--';
                        $key = $synonyms[$key] ?? $key;
                        if (!isset($ordersByStatus[$key])) {
                            $ordersByStatus[$key] = [
                                'name' => $row->raw_name ?: $key,
                                'cnt'  => 0,
                            ];
                        }
                        $ordersByStatus[$key]['cnt'] += (int)$row->cnt;
                    }
                    $ordersByStatus = collect($ordersByStatus)->sortKeys()->map(function($v, $k){
                        $label = str_replace(['-','_'], ' ', $v['name']);
                        $label = trim($label);
                        $v['label'] = $label === '' ? '--' : $label;
                        return $v;
                    });

                    // Order items by status (canonicalize and merge synonyms)
                    $orderItemsRaw = OrderProduct::query()
                        ->selectRaw("LOWER(TRIM(COALESCE(NULLIF(status,''), '--'))) as key_status, COUNT(*) as cnt")
                        ->groupByRaw("LOWER(TRIM(COALESCE(NULLIF(status,''), '--')))")
                        ->orderByDesc('cnt')
                        ->get();

                    $orderItemsByStatus = [];
                    foreach ($orderItemsRaw as $row) {
                        $key = $row->key_status ?: '--';
                        $key = $synonyms[$key] ?? $key;
                        $orderItemsByStatus[$key] = [
                            'label' => $key === '--' ? '--' : str_replace(['-','_'], ' ', $key),
                            'cnt' => (int)$row->cnt,
                        ];
                    }
                    $orderItemsByStatus = collect($orderItemsByStatus)->sortKeys();
                @endphp

                <div class="row g-3">
                    <div class="col-sm-6 col-lg-6 col-12">
                        <div class="card stand-summary-card h-100">
                            <div class="stand-header"><strong>Overview</strong></div>
                            <div class="stand-body">
                                <div class="stand-detail"><div class="stand-detail-label">Total Orders</div><div class="stand-detail-value">{{ number_format($ordersTotal) }}</div></div>
                                <div class="stand-detail"><div class="stand-detail-label">Total Order Items</div><div class="stand-detail-value">{{ number_format($orderItemsTotal) }}</div></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-6 col-12">
                        <div class="card stand-summary-card h-100">
                            <div class="stand-header"><strong>Orders by Status</strong></div>
                            <div class="stand-body">
                                @forelse($ordersByStatus as $row)
                                    @php
                                        $label = ($row['label'] ?? ($row['name'] ?? '—'));
                                        $bg = \App\Helpers\OrderStatusColors::hex($label);
                                        $tc = \App\Helpers\OrderStatusColors::textColor($bg);
                                    @endphp
                                    <div class="stand-detail">
                                        <span class="status-badge" style="background: {{ $bg }}; color: {{ $tc }};">{{ ucwords($label) }}</span>
                                        <span class="stand-detail-value">{{ number_format($row['cnt']) }}</span>
                                    </div>
                                @empty
                                    <div class="text-muted">No data</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-12 col-lg-12 col-12">
                        <div class="card stand-summary-card h-100">
                            <div class="stand-header"><strong>Order Items by Status</strong></div>
                            <div class="stand-body">
                                <div class="row">
                                    @forelse($orderItemsByStatus as $row)
                                        @php
                                            $label = ($row['label'] ?? '—');
                                            $bg = \App\Helpers\OrderStatusColors::hex($label);
                                            $tc = \App\Helpers\OrderStatusColors::textColor($bg);
                                        @endphp
                                        <div class="col-6 col-md-4 col-lg-3 mb-2">
                                            <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                                <span class="status-badge" style="background: {{ $bg }}; color: {{ $tc }};">{{ ucwords($label) }}</span>
                                                <span class="fw-semibold">{{ number_format($row['cnt']) }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12 text-muted">No data</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Section --}}
            {{-- Notifications Section --}}
            <div class="col-md-4 box-col-12 d-md-block d-none activity-group box-col-none">
                <div class="card">
                    <div class="card-header card-no-border total-revenue d-flex justify-content-between align-items-center">
                        <h4>Notifications</h4>
                        <form action="{{ route('notifications.markAllRead') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="ri-check-double-line"></i> Mark all as read
                            </button>
                        </form>
                    </div>
                    <hr>
                    <div class="card-body pt-0">
                        <div class="activity-log-card">
                            <ul class="list-unstyled mb-0">
                                @forelse($notifications as $notification)
                                    <li class="activity-log {{ $notification->read_at ? '' : 'bg-light text-black' }} py-2 px-2 rounded-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="me-2 d-flex align-items-center justify-content-center" style="min-width: 36px; min-height: 36px;">
                                                @switch($notification->type)
                                                    @case('App\Notifications\StandReservationExpiredNotification')
                                                        <i class="ri-alarm-warning-line text-danger" style="font-size: 24px;"></i>
                                                        @break
                                                    @case('App\Notifications\StandNeedsInvestigationNotification')
                                                        <i class="ri-error-warning-line text-warning" style="font-size: 24px;"></i>
                                                        @break
                                                    @default
                                                        <i class="ri-notification-3-line text-info" style="font-size: 24px;"></i>
                                                @endswitch
                                            </span>
                                            <div class="flex-grow-1">
                                                <div class="common-space user-id">
                                                    <h6 class="f-w-500 f-12 mb-0 text-primary">{{ $notification->data['title'] ?? 'Notification' }}</h6>
                                                    <span class="f-light f-w-500 f-12">
                                                        {{ $notification->created_at->diffForHumans() }}
                                                    </span>
                                                </div>
                                                <div class="mb-2 f-12">
                                                    {{ $notification->data['message'] ?? 'You have a new notification.' }}
                                                </div>
                                                @if(!$notification->read_at)
                                                    <form action="{{ route('notifications.markRead', $notification->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-xs btn-link p-0">Mark as read</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li>
                                        <div class="text-center p-3 text-muted">No notifications found.</div>
                                    </li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

