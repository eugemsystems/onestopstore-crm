@extends('layouts.simple.master')

@section('title', 'Analytics Dashboard')

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css">
    <style>
        .analytics-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            background: white;
            overflow: hidden;
        }
        .analytics-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        .stat-box {
            padding: 1.5rem;
            border-left: 4px solid;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .stat-box.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-box.success { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-box.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-box.warning { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-box.danger { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        .stat-icon {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 20px;
        }
        .chart-container {
            position: relative;
            padding: 1.5rem;
            background: white;
            border-radius: 10px;
        }
        .period-selector {
            margin-bottom: 2rem;
        }
        .period-btn {
            margin: 0 5px;
        }
        .active-users-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .device-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        .progress-thin {
            height: 8px;
        }
    </style>
@endsection

@section('breadcrumb-title')
    <h3>Analytics Dashboard</h3>
@endsection

@section('breadcrumb-items')
    <li class="breadcrumb-item">Dashboard</li>
    <li class="breadcrumb-item active">Analytics</li>
@endsection

@section('content')
<div class="container-fluid">

    @if(isset($error))
        <div class="alert alert-danger">
            <strong>Error:</strong> {{ $error }}
        </div>
    @endif

    <!-- Period Selector -->
    <div class="row period-selector">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4>Website Analytics</h4>
                <div>
                    <span class="active-users-badge me-3" id="activeUsersBadge">
                        <i class="fa fa-circle text-success"></i> <span id="activeUsersCount">0</span> Active Users
                    </span>
                    <div class="btn-group" role="group">
                        <a href="{{ route('analytics.dashboard', ['period' => '24h']) }}"
                           class="btn btn-sm {{ $period === '24h' ? 'btn-primary' : 'btn-outline-primary' }}">24 Hours</a>
                        <a href="{{ route('analytics.dashboard', ['period' => '7d']) }}"
                           class="btn btn-sm {{ $period === '7d' ? 'btn-primary' : 'btn-outline-primary' }}">7 Days</a>
                        <a href="{{ route('analytics.dashboard', ['period' => '30d']) }}"
                           class="btn btn-sm {{ $period === '30d' ? 'btn-primary' : 'btn-outline-primary' }}">30 Days</a>
                        <a href="{{ route('analytics.dashboard', ['period' => '90d']) }}"
                           class="btn btn-sm {{ $period === '90d' ? 'btn-primary' : 'btn-outline-primary' }}">90 Days</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($stats)
    <!-- Overview Stats -->
    <div class="row">
        <div class="col-xl-3 col-sm-6">
            <div class="stat-box primary position-relative">
                <i class="fa fa-users stat-icon"></i>
                <h2 class="stat-value">{{ number_format($stats['overview']['total_sessions']) }}</h2>
                <p class="stat-label">Total Sessions</p>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-box success position-relative">
                <i class="fa fa-eye stat-icon"></i>
                <h2 class="stat-value">{{ number_format($stats['overview']['total_page_views']) }}</h2>
                <p class="stat-label">Page Views</p>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-box info position-relative">
                <i class="fa fa-user-check stat-icon"></i>
                <h2 class="stat-value">{{ number_format($stats['overview']['unique_visitors']) }}</h2>
                <p class="stat-label">Unique Visitors</p>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="stat-box warning position-relative">
                <i class="fa fa-mouse-pointer stat-icon"></i>
                <h2 class="stat-value">{{ number_format($stats['overview']['total_events']) }}</h2>
                <p class="stat-label">User Events</p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8">
            <div class="analytics-card">
                <div class="chart-container">
                    <h5 class="mb-4">Traffic Overview</h5>
                    <canvas id="trafficChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="analytics-card">
                <div class="chart-container">
                    <h5 class="mb-4">Device Types</h5>
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Pages and Referrers -->
    <div class="row">
        <div class="col-xl-6">
            <div class="table-card">
                <h5 class="mb-3">Top Pages</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th class="text-end">Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stats['top_pages'] as $page)
                            <tr>
                                <td>
                                    <strong>{{ $page['page_title'] ?? 'Untitled' }}</strong><br>
                                    <small class="text-muted">{{ $page['path'] }}</small>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-primary">{{ number_format($page['views']) }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="2" class="text-center">No data available</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="table-card">
                <h5 class="mb-3">Top Referrers</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Referrer</th>
                                <th class="text-end">Visits</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stats['referrers'] as $referrer)
                            <tr>
                                <td>{{ $referrer['referrer'] }}</td>
                                <td class="text-end">
                                    <span class="badge bg-info">{{ number_format($referrer['count']) }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="2" class="text-center">No data available</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Devices, Browsers, OS -->
    <div class="row mt-4">
        <div class="col-xl-4">
            <div class="table-card">
                <h5 class="mb-3"><i class="fa fa-mobile device-icon"></i>Devices</h5>
                <div class="list-group">
                    @foreach($stats['devices'] as $device)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-capitalize">{{ $device['device_type'] ?? 'Unknown' }}</span>
                        <span class="badge bg-primary rounded-pill">{{ number_format($device['count']) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="table-card">
                <h5 class="mb-3"><i class="fa fa-globe device-icon"></i>Browsers</h5>
                <div class="list-group">
                    @foreach($stats['browsers'] as $browser)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $browser['browser'] ?? 'Unknown' }}</span>
                        <span class="badge bg-success rounded-pill">{{ number_format($browser['count']) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="table-card">
                <h5 class="mb-3"><i class="fa fa-desktop device-icon"></i>Operating Systems</h5>
                <div class="list-group">
                    @foreach($stats['os'] as $os)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $os['os'] ?? 'Unknown' }}</span>
                        <span class="badge bg-info rounded-pill">{{ number_format($os['count']) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Abandonments -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="analytics-card">
                <div class="p-4">
                    <h5 class="mb-4"><i class="fa fa-shopping-cart me-2"></i>Cart Abandonment Analysis</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-box danger position-relative">
                                <h3 class="stat-value">{{ number_format($stats['cart_abandonments']['total']) }}</h3>
                                <p class="stat-label">Total Abandonments</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-box success position-relative">
                                <h3 class="stat-value">{{ number_format($stats['cart_abandonments']['recovered']) }}</h3>
                                <p class="stat-label">Recovered</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-box warning position-relative">
                                <h3 class="stat-value">${{ number_format($stats['cart_abandonments']['total_value'], 2) }}</h3>
                                <p class="stat-label">Lost Revenue</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-box info position-relative">
                                @php
                                    $recoveryRate = $stats['cart_abandonments']['total'] > 0
                                        ? ($stats['cart_abandonments']['recovered'] / $stats['cart_abandonments']['total']) * 100
                                        : 0;
                                @endphp
                                <h3 class="stat-value">{{ number_format($recoveryRate, 1) }}%</h3>
                                <p class="stat-label">Recovery Rate</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6>Abandonment by Stage</h6>
                        <div class="row">
                            @foreach($stats['cart_abandonments']['by_stage'] as $stage)
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h4>{{ number_format($stage['count']) }}</h4>
                                        <p class="text-muted mb-0 text-capitalize">{{ str_replace('_', ' ', $stage['abandonment_stage']) }}</p>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Events -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="table-card">
                <h5 class="mb-3"><i class="fa fa-bolt me-2"></i>Top User Events</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Type</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats['top_events'] as $event)
                            <tr>
                                <td>{{ $event['event_name'] }}</td>
                                <td><span class="badge bg-secondary">{{ $event['event_type'] }}</span></td>
                                <td class="text-end">
                                    <span class="badge bg-primary">{{ number_format($event['count']) }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-info">
        <strong>No analytics data available.</strong> Start tracking to see analytics.
    </div>
    @endif
</div>
@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch and update active users count
    function updateActiveUsers() {
        fetch('{{ route('analytics.active-users') }}')
            .then(response => response.json())
            .then(data => {
                document.getElementById('activeUsersCount').textContent = data.active_users || 0;
            })
            .catch(error => console.error('Error fetching active users:', error));
    }

    updateActiveUsers();
    setInterval(updateActiveUsers, 30000); // Update every 30 seconds

    @if($timeseries)
    // Traffic Chart
    const trafficCtx = document.getElementById('trafficChart');
    if (trafficCtx) {
        new Chart(trafficCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode(collect($timeseries['page_views'])->pluck('period')) !!},
                datasets: [
                    {
                        label: 'Page Views',
                        data: {!! json_encode(collect($timeseries['page_views'])->pluck('count')) !!},
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Sessions',
                        data: {!! json_encode(collect($timeseries['sessions'])->pluck('count')) !!},
                        borderColor: 'rgb(245, 87, 108)',
                        backgroundColor: 'rgba(245, 87, 108, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    @endif

    @if($stats)
    // Device Chart
    const deviceCtx = document.getElementById('deviceChart');
    if (deviceCtx) {
        const deviceData = {!! json_encode($stats['devices']) !!};
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: deviceData.map(d => d.device_type || 'Unknown'),
                datasets: [{
                    data: deviceData.map(d => d.count),
                    backgroundColor: [
                        'rgb(102, 126, 234)',
                        'rgb(245, 87, 108)',
                        'rgb(74, 172, 254)',
                        'rgb(250, 112, 154)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }
    @endif
});
</script>
@endsection

