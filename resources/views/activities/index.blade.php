@extends('layouts.simple.master')

@section('title', 'Activities')

@section('main_content')
    <!-- Breadcrumb starts-->
    <div class="container-fluid">
        <div class="page-title">
            <div class="row">
                <div class="col-6">
                    <h4>Activities</h4>
                </div>
                <div class="col-6">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">
                                <svg class="stroke-icon">
                                    <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-home') }}"></use>
                                </svg></a></li>
                        <li class="breadcrumb-item active">Activities</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="row size-column">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Recent Activity</h5>
                        <form method="get" class="d-flex" style="gap:8px;">
                            <input type="text" name="q" value="{{ request('q','') }}" class="form-control form-control-sm" placeholder="Search..." />
                            <button class="btn btn-sm btn-primary" type="submit">Search</button>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                <tr>
                                    <th style="width: 170px;">Date</th>
                                    <th>Description</th>
                                    <th>Event</th>
                                    <th>Causer</th>
                                    <th>Subject</th>
                                    <th>Device</th>
                                    <th>Details</th>
                                </tr>
                                </thead>
                                <tbody>
                                @isset($activities)
                                    @forelse($activities as $a)
                                        <tr>
                                            <td>{{ optional($a->created_at)->format('Y-m-d H:i:s') }}</td>
                                            <td>{{ $a->description }}</td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $a->event ?? '-' }}</span>
                                                <div class="text-muted f-12">{{ $a->log_name ?? 'default' }}</div>
                                            </td>
                                            <td>
                                                @php($causer = $a->causer)
                                                @if($causer)
                                                    {{ $causer->full_name ?? $causer->name ?? $causer->email ?? ('ID #'.$a->causer_id) }}
                                                    <div class="text-muted f-12">{{ class_basename($a->causer_type) }} #{{ $a->causer_id }}</div>
                                                @else
                                                    <span class="text-muted">Anonymous</span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ class_basename($a->subject_type) ?: '-' }}
                                                @if($a->subject_id)
                                                    #{{ $a->subject_id }}
                                                @endif
                                            </td>
                                            <td>
                                                @php($props = $a->properties ?? [])
                                                @php($props = is_object($props) && method_exists($props,'toArray') ? $props->toArray() : $props)
                                                @php($ua = $props['user_agent'] ?? null)
                                                @php($ip = $props['ip'] ?? null)
                                                @php($hostname = $props['computer_name'] ?? ($props['hostname'] ?? null))
                                                <div class="small">
                                                    @if($ua)
                                                        <div><strong>Browser:</strong> <span class="text-break">{{ $ua }}</span></div>
                                                    @endif
                                                    @if($hostname)
                                                        <div><strong>Host:</strong> <span class="text-break">{{ $hostname }}</span></div>
                                                    @endif
                                                    @if($ip)
                                                        <div><strong>IP:</strong> {{ $ip }}</div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @php($props = $a->properties ?? [])
                                                @php($props = is_object($props) && method_exists($props,'toArray') ? $props->toArray() : $props)
                                                @if(!empty($props))
                                                    <details>
                                                        <summary>View</summary>
                                                        <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </details>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center p-3 text-muted">No activity found.</td>
                                        </tr>
                                    @endforelse
                                @else
                                    <tr>
                                        <td colspan="6" class="text-center p-3 text-muted">No activity data available.</td>
                                    </tr>
                                @endisset
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        @isset($activities)
                            {{ $activities->links() }}
                        @endisset
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
