<?php

use App\Models\Order;
use App\Models\OrderStatus;
use App\Helpers\OrderStatusColors;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\UserOrderView;

new #[Layout('layouts.simple.master')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $selectedId = null;

    public bool $drawerOpen = false; // kept but drawer is JS-controlled in the view

    public string $paginationTheme = 'bootstrap';
    public array $queryString = ['search' => ['except' => '']];

    // Sorting
    public string $sortBy = 'date'; // order_number | order_status | customer | date
    public string $sortDir = 'desc'; // asc | desc

    public function with(): array
    {
        if (!auth()->user()?->can('view orders')) { abort(403); }
        $q = Order::query()->with(['order_items','order_status'])->withCount('order_items');
        //dd($q->first());

        $search = trim($this->search) !== '' ? $this->search : (string) request('search', '');
        if (trim($search) !== '') {
            $term = '%' . $search . '%';
            $q->where(function ($sub) use ($term) {
                $sub->where('id', 'like', $term)
                    ->orWhere('order_number', 'like', $term)
                    ->orWhere('payment_status', 'like', $term)
                    ->orWhere('currency', 'like', $term)
                    ->orWhere('consumer_id', 'like', $term);
            });
        }

        // Optional filter by order status id from query string
        $statusFilter = request('status');
        if ($statusFilter !== null && $statusFilter !== '') {
            $q->where('order_status_id', (int) $statusFilter);
        }

        // Resolve sorting from query params
        $sortReq = request('sort');
        $dirReq = request('dir');
        $allowedSorts = ['order_number','order_status','customer','date'];
        if ($sortReq && in_array($sortReq, $allowedSorts, true)) {
            $this->sortBy = $sortReq;
        }
        $dirLower = strtolower((string) $dirReq);
        if (in_array($dirLower, ['asc','desc'], true)) {
            $this->sortDir = $dirLower;
        }

        // Sorting
        $by = $this->sortBy ?? 'date';
        $dir = strtolower($this->sortDir ?? 'desc') === 'asc' ? 'asc' : 'desc';
        if ($by === 'order_number') {
            $q->orderBy('orders.order_number', $dir);
        } elseif ($by === 'order_status') {
            $q->leftJoin('order_statuses', 'order_statuses.id', '=', 'orders.order_status_id')
              ->select('orders.*')
              ->orderByRaw('COALESCE(order_statuses.name, orders.order_status) ' . $dir);
        } elseif ($by === 'customer') {
            $q->orderBy('orders.consumer_name', $dir)->orderBy('orders.consumer_id', $dir);
        } else { // date
            $q->orderBy('orders.created_at', $dir);
        }

        // Annotate whether current user has viewed each order (single query)
        $uid = auth()->id() ?: 0;
        $q->addSelect([
            'viewed_by_me' => DB::table('user_order_views')
                ->selectRaw('1')
                ->whereColumn('user_order_views.order_id', 'orders.id')
                ->where('user_id', $uid)
                ->limit(1)
        ]);

        $statuses = OrderStatus::query()->orderBy('sequence')->orderBy('name')->get(['id','name'])->toArray();

        return [
            'orders' => $q->paginate(15),
            'statuses' => $statuses,
        ];
    }

    public function setSort(string $by): void
    {
        $allowed = ['order_number','order_status','customer','date'];
        if (!in_array($by, $allowed, true)) return;
        if ($this->sortBy === $by) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $by;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function runBackfill(): void
    {
        try {
            $exit = Artisan::call('orders:backfill');
            $output = trim(Artisan::output());
            $msg = $output !== '' ? $output : ('Orders backfill completed. Exit code: ' . $exit);
            $this->dispatch('success', $msg);
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Backfill failed: ' . $e->getMessage());
        }
    }

    public function markOrderOpened(int $orderId): void
    {
        try {
            $uid = (int) (auth()->id() ?? 0);
            if ($uid > 0 && $orderId > 0) {
                UserOrderView::updateOrCreate(
                    ['user_id' => $uid, 'order_id' => $orderId],
                    ['viewed_at' => now()]
                );
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    // Helpers (Volt-friendly, used previously in Livewire drawer)
    public function orderAmount($order): ?float
    {
        // For wallet payments, if total is 0 but amount/subtotal exists, use that instead
        $total = $order->total ?? null;
        $amount = $order->amount ?? null;
        $subtotal = $order->subtotal ?? null;

        // If total is 0 or null, fall back to amount (subtotal before wallet deduction)
        if ($total === null || $total == 0) {
            $v = $amount ?? $subtotal ?? $total;
        } else {
            $v = $total ?? $amount ?? $subtotal;
        }

        return is_null($v) ? null : (float) $v;
    }

    public function formatMoney($value, ?string $currency): string
    {
        if ($value === null) return '-';
        $n = (float) $value;
        $sym = $currency ?: '$';
        return $sym . number_format($n, 2);
    }
}; ?>

<div>
    <style>
        .drawer-overlay { background: rgba(0,0,0,.35); }
        .drawer-panel { width: 50vw; max-width: 100vw; transition: transform .25s ease; background:#fff; }

        /* Colored tab buttons */
        .drawer-tabs .nav-link { font-weight: 600; border: 1px solid transparent; border-radius: .5rem .5rem 0 0; }
        .drawer-tabs .nav-link:not(.active):hover { background: rgba(0,0,0,.03); }
        .drawer-tabs .nav-link[data-tab-target="details"].active,
        .drawer-tabs .nav-link[data-tab-target="address"].active,
        .drawer-tabs .nav-link[data-tab-target="items"].active,
        .drawer-tabs .nav-link[data-tab-target="history"].active { background: rgb(36,68,126); color: white; }

        /* ── Premium Orders Table ── */
        .orders-card { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .orders-card-header { background: linear-gradient(135deg, #0f1923 0%, #1a2f4a 60%, #0f3460 100%); border: none; padding: 1.1rem 1.5rem; }
        .orders-card-header h5 { color: #e2e8f0; font-weight: 700; letter-spacing: .02em; margin: 0; }
        .orders-table { margin: 0; border-collapse: separate; border-spacing: 0; table-layout: auto; }
        .orders-table thead tr.col-heads th { background: linear-gradient(135deg, #1a2740 0%, #243b55 100%); color: #cbd5e1 !important; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; padding: .8rem .9rem; border: none; white-space: nowrap; }
        .orders-table thead tr.col-heads th .sort-btn { color: #94a3b8 !important; text-decoration: none; font: inherit; font-weight: 700; background: none; border: none; cursor: pointer; }
        .orders-table thead tr.col-heads th .sort-btn:hover { color: #e2e8f0 !important; }
        .orders-table thead tr.filter-row th { background: #1e2e44; padding: .35rem .6rem; border: none; }
        .orders-filter-input { width: 100%; background: rgba(255,255,255,.07) !important; border: 1px solid rgba(255,255,255,.15) !important; color: #e2e8f0 !important; border-radius: 6px; font-size: .76rem; padding: .28rem .55rem; }
        .orders-filter-input::placeholder { color: rgba(255,255,255,.35); }
        .orders-filter-input:focus { background: rgba(255,255,255,.12) !important; border-color: rgba(99,179,237,.5) !important; box-shadow: 0 0 0 2px rgba(99,179,237,.18); outline: none; }
        .orders-table tbody tr { cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background .13s ease, transform .13s ease; }
        .orders-table tbody tr:hover { background: #f0f7ff !important; transform: translateX(2px); }
        .orders-table td { padding: .65rem .9rem; vertical-align: middle; border: none; }
    </style>

    <div>
        <div class="content__header content__boxed overlapping">
            <div class="content__wrap">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Orders</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="content__boxed">
            <div class="content__wrap">
                <div class="card orders-card">
                    <div class="orders-card-header">
                        <h5><i class="bi bi-bag-check me-2"></i>Orders</h5>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <form id="ordersSearchForm" method="GET">
                            <table class="table orders-table mb-0">
                                <thead>
                                {{-- Column headings --}}
                                <tr class="col-heads">
                                    <th style="width:130px;">
                                        <button type="button" class="sort-btn" wire:click.prevent="setSort('order_number')">
                                            Order # @if($sortBy==='order_number'){{ $sortDir==='asc'?'↑':'↓' }}@endif
                                        </button>
                                    </th>
                                    <th style="width:200px;">
                                        <button type="button" class="sort-btn" wire:click.prevent="setSort('order_status')">
                                            Status @if($sortBy==='order_status'){{ $sortDir==='asc'?'↑':'↓' }}@endif
                                        </button>
                                    </th>
                                    <th style="width:140px;">Amount</th>
                                    <th>
                                        <button type="button" class="sort-btn" wire:click.prevent="setSort('customer')">
                                            Customer @if($sortBy==='customer'){{ $sortDir==='asc'?'↑':'↓' }}@endif
                                        </button>
                                    </th>
                                    <th style="width:165px;">
                                        <button type="button" class="sort-btn" wire:click.prevent="setSort('date')">
                                            Date @if($sortBy==='date'){{ $sortDir==='asc'?'↑':'↓' }}@endif
                                        </button>
                                    </th>
                                </tr>
                                {{-- Inline filter row --}}
                                <tr class="filter-row">
                                    <th>
                                        <input type="text" name="search" class="orders-filter-input" placeholder="Search #..." autocomplete="off" value="{{ request('search','') }}">
                                    </th>
                                    <th>
                                        <select name="status" class="orders-filter-input" onchange="this.closest('form').submit()">
                                            <option value="">All statuses</option>
                                            @foreach(($statuses ?? []) as $s)
                                                <option value="{{ $s['id'] }}" @selected((string)request('status','') === (string)$s['id'])>{{ $s['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @php $statusColors = OrderStatusColors::map(); @endphp
                                @forelse($orders as $o)
                                    <tr data-order-id="{{ $o->id }}">
                                        {{-- Order # --}}
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                @if(empty($o->viewed_by_me))
                                                    <span class="badge text-bg-danger" style="font-size:.65rem;padding:1px 4px;">NEW</span>
                                                @endif
                                                <span class="fw-bold text-primary">#{{ $o->order_number }}</span>
                                            </div>
                                            <div class="small text-muted">{{ $o->order_items_count ?? 0 }} item{{ ($o->order_items_count ?? 0) == 1 ? '' : 's' }}</div>
                                        </td>
                                        {{-- Status --}}
                                        <td>
                                            @php
                                                $statusName = (string)($o->order_status?->name ?? '');
                                                if ($statusName === '' && isset($o->order_status_id)) {
                                                    $sr = collect($statuses ?? [])->firstWhere('id', $o->order_status_id);
                                                    $statusName = (string)($sr['name'] ?? '');
                                                }
                                                $s    = strtolower(trim($statusName));
                                                $label = $statusName !== '' ? Str::title($statusName) : '—';
                                                $color = \App\Helpers\OrderStatusColors::hex($s);
                                                $textColor = \App\Helpers\OrderStatusColors::textColor($color);
                                            @endphp
                                            <span class="badge w-100 d-block" style="background-color:{{ $color }};color:{{ $textColor }};">{{ $label }}</span>
                                        </td>
                                        {{-- Amount --}}
                                        <td><strong>{{ $this->formatMoney(((float)($this->orderAmount($o) ?? 0)) * ((float)($o->exchange_rate ?? 1)), $o->currency_symbol ?? $o->currency) }}</strong></td>
                                        {{-- Customer --}}
                                        <td>
                                            <div class="fw-semibold">{{ $o->consumer_name ?: '#'.$o->consumer_id }}</div>
                                            @if(!empty($o->consumer_name))
                                                <div class="small text-muted">#{{ $o->consumer_id }}</div>
                                            @endif
                                        </td>
                                        {{-- Date --}}
                                        <td class="small text-muted">{{ $o->created_at?->format('M d, Y') ?? '-' }}<br>{{ $o->created_at?->format('H:i') ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox display-6 d-block mb-2"></i>No orders found.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                            </form>
                        </div>
                        <div class="px-3 py-3">
                            {{ $orders->appends(request()->except('page'))->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Drawer overlay -->
        <div id="orderDrawerOverlay" class="position-fixed top-0 start-0 w-100 h-100 drawer-overlay" style="z-index:1050; display:none;"></div>

        <!-- Drawer panel -->
        <div id="orderDrawerPanel" class="position-fixed top-0 end-0 h-100 shadow drawer-panel" style="z-index:1051; transform: translateX(100%);">
            <div class="d-flex align-items-center justify-content-between border-bottom px-3 py-3">
                <h6 class="mb-0">Order Details</h6>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-link waybill-print" id="drawerWaybillPrint" title="Print Waybill" data-order-id="" aria-label="Print Waybill" onclick="window.printCurrentWaybill && window.printCurrentWaybill()">
                        <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 17.9827C4.44655 17.9359 3.51998 17.7626 2.87868 17.1213C2 16.2426 2 14.8284 2 12C2 9.17157 2 7.75736 2.87868 6.87868C3.75736 6 5.17157 6 8 6H16C18.8284 6 20.2426 6 21.1213 6.87868C22 7.75736 22 9.17157 22 12C22 14.8284 22 16.2426 21.1213 17.1213C20.48 17.7626 19.5535 17.9359 18 17.9827" stroke="#1C274C" stroke-width="1.5"/>
                            <path opacity="0.5" d="M9 10H6" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M19 15L5 15" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 15V16C18 18.8284 18 20.2426 17.1213 21.1213C16.2426 22 14.8284 22 12 22C9.17157 22 7.75736 22 6.87868 21.1213C6 20.2426 6 18.8284 6 16V15" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"/>
                            <path opacity="0.5" d="M17.9827 6C17.9359 4.44655 17.7626 3.51998 17.1213 2.87868C16.2427 2 14.8284 2 12 2C9.17158 2 7.75737 2 6.87869 2.87868C6.23739 3.51998 6.06414 4.44655 6.01733 6" stroke="#1C274C" stroke-width="1.5"/>
                            <circle opacity="0.5" cx="17" cy="10" r="1" fill="#1C274C"/>
                        </svg>
                    </button>
                    <button type="button" class="btn btn-sm btn-link waybill-download" id="drawerWaybillDownload" title="Download Waybill (PDF)" data-order-id="" aria-label="Download Waybill" onclick="window.downloadCurrentWaybill && window.downloadCurrentWaybill()">
                        <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.5" d="M17 9.00195C19.175 9.01406 20.3529 9.11051 21.1213 9.8789C22 10.7576 22 12.1718 22 15.0002V16.0002C22 18.8286 22 20.2429 21.1213 21.1215C20.2426 22.0002 18.8284 22.0002 16 22.0002H8C5.17157 22.0002 3.75736 22.0002 2.87868 21.1215C2 20.2429 2 18.8286 2 16.0002L2 15.0002C2 12.1718 2 10.7576 2.87868 9.87889C3.64706 9.11051 4.82497 9.01406 7 9.00195" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M12 2L12 15M12 15L9 11.5M12 15L15 11.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <a role="button" id="orderDrawerClose" class="" aria-label="Close">
                        <svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12ZM8.96963 8.96965C9.26252 8.67676 9.73739 8.67676 10.0303 8.96965L12 10.9393L13.9696 8.96967C14.2625 8.67678 14.7374 8.67678 15.0303 8.96967C15.3232 9.26256 15.3232 9.73744 15.0303 10.0303L13.0606 12L15.0303 13.9696C15.3232 14.2625 15.3232 14.7374 15.0303 15.0303C14.7374 15.3232 14.2625 15.3232 13.9696 15.0303L12 13.0607L10.0303 15.0303C9.73742 15.3232 9.26254 15.3232 8.96965 15.0303C8.67676 14.7374 8.67676 14.2625 8.96965 13.9697L10.9393 12L8.96963 10.0303C8.67673 9.73742 8.67673 9.26254 8.96963 8.96965Z" fill="#1C274C"/>
                        </svg>
                    </a>
                </div>
            </div>

            <div id="orderDrawerBody" class="p-3 overflow-auto h-100" style="padding-bottom:6rem;">
                <div class="text-muted">Select an order to see details.</div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const ORDER_STATUSES = @json($statuses ?? []);
            const CSRF_TOKEN = '{{ csrf_token() }}';
            (function(){
                const overlay = document.getElementById('orderDrawerOverlay');
                const panel   = document.getElementById('orderDrawerPanel');
                const closeBtn= document.getElementById('orderDrawerClose');
                const body    = document.getElementById('orderDrawerBody');

                if (!overlay || !panel || !body) return;

                const openDrawer = () => {
                    overlay.style.display = '';
                    panel.style.transform = 'translateX(0)';
                };
                const closeDrawer = () => {
                    overlay.style.display = 'none';
                    panel.style.transform = 'translateX(100%)';
                };

                overlay.addEventListener('click', closeDrawer);
                closeBtn?.addEventListener('click', closeDrawer);

                document.addEventListener('click', async (e) => {
                    // Save order status inside the drawer
                    const saveBtn = e.target.closest('#saveOrderStatusBtn');
                    if (saveBtn && body.contains(saveBtn)) {
                        e.preventDefault();
                        const orderId = saveBtn.getAttribute('data-order-id');
                        const orderNumberAttr = saveBtn.getAttribute('data-order-number') || null;
                        const sel = body.querySelector('#orderStatusSelect');
                        if (!orderId || !sel) return;
                        const val = sel.value;
                        if (!val) return;
                        const spinner = body.querySelector('#orderStatusSaveSpinner');
                        if (spinner) spinner.style.display = '';
                        try {
                            const updateUrl = `{{ route('app.orders.update-status', ['order' => '___ID___']) }}`.replace('___ID___', orderId);
                            const resp = await fetch(updateUrl, {
                                method: 'PUT',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': CSRF_TOKEN,
                                },
                                body: JSON.stringify({ order_status_id: Number(val), order_number: orderNumberAttr || undefined })
                            });
                            const data = await resp.json().catch(() => ({}));
                            if (!resp.ok || data.ok === false) throw new Error(data.message || 'Failed to update status');

                            // Update table badge for this order
                            try {
                                const tr = document.querySelector(`tr[data-order-id="${orderId}"]`);
                                if (tr) {
                                    const tds = tr.querySelectorAll('td');
                                    const statusTd = tds[1]; // Status is now column index 1 (Items column removed)
                                    if (statusTd) statusTd.innerHTML = badgeStatus((data.status && data.status.name) || '');
                                }
                            } catch(_){}

                            // Provide basic feedback
                            saveBtn.classList.remove('btn-primary');
                            saveBtn.classList.add('btn-success');
                            saveBtn.textContent = 'Saved';
                            setTimeout(() => { saveBtn.textContent = 'Save'; saveBtn.classList.add('btn-primary'); saveBtn.classList.remove('btn-success'); }, 1500);
                        } catch (err) {
                            console.error(err);
                            alert(err.message || 'Failed to update order status');
                        } finally {
                            if (spinner) spinner.style.display = 'none';
                        }
                        return;
                    }

                    // Tab switching inside the drawer
                    const tab = e.target.closest('[data-tab-target]');
                    if (tab && body.contains(tab)) {
                        e.preventDefault();
                        const target = tab.getAttribute('data-tab-target');
                        const links = body.querySelectorAll('.drawer-tabs .nav-link');
                        links.forEach(l => l.classList.toggle('active', l === tab));
                        const panes = body.querySelectorAll('.drawer-tab-pane');
                        panes.forEach(p => {
                            const match = p.getAttribute('data-tab') === target;
                            p.classList.toggle('active', match);
                            p.classList.toggle('show', match);
                        });
                        return;
                    }

                    // Row click to open drawer
                    const tr = e.target.closest('tr[data-order-id]');
                    if (!tr) return;

                    const id = tr.getAttribute('data-order-id');
                    try {
                        const url = `{{ route('app.orders.show', ['order' => '___ID___']) }}`.replace('___ID___', id);
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Failed to fetch order');
                        const order = await res.json();
                        renderOrder(order);
                        try { window.CURRENT_ORDER = order; } catch(_) {}
                        try {
                            document.getElementById('drawerWaybillPrint')?.setAttribute('data-order-id', id);
                            document.getElementById('drawerWaybillDownload')?.setAttribute('data-order-id', id);
                        } catch(_) {}
                        try { if (window.bindWaybillButtons) window.bindWaybillButtons(); } catch(_) {}
                        openDrawer();
                        // mark as viewed and remove NEW badge
                        try {
                            const markUrl = `{{ route('app.orders.mark-viewed', ['order' => '___ID___']) }}`.replace('___ID___', id);
                            await fetch(markUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' } });
                            const row = document.querySelector(`tr[data-order-id="${id}"]`);
                            row?.querySelector('[data-new-badge]')?.remove();
                        } catch(_) {}
                    } catch (err) {
                        console.error(err);
                    }
                });

                function money(v, cur){
                    if (v === null || v === undefined) return '-';
                    const n = Number(v) || 0;
                    const sym = cur || '$';
                    return `${sym}${n.toFixed(2)}`;
                }

                function safeDate(v){
                    try{ return (v ? new Date(v).toLocaleString() : '-') }catch(_){ return v || '-' }
                }

                function badgePayment(p){
                    const v = String(p || '').toLowerCase();
                    switch(v){
                        case 'paid': return '<span class="badge bg-success">Paid</span>';
                        case 'refunded': return '<span class="badge bg-danger">Refunded</span>';
                        case 'unpaid': return '<span class="badge bg-warning">Unpaid</span>';
                        case 'pending': return '<span class="badge bg-warning-subtle text-warning">Pending</span>';
                        case 'failed': return '<span class="badge bg-danger-subtle text-danger">Failed</span>';
                        default: return `<span class="badge bg-info-subtle text-info">${p ?? '—'}</span>`;
                    }
                }

                const STATUS_COLORS = @json($statusColors ?? []);
                function titleCase(str){
                    return String(str||'').replace(/\w\S*/g, (w)=>w.charAt(0).toUpperCase()+w.slice(1).toLowerCase());
                }
                function textColorFor(hex){
                    try{
                        const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                        if (!m) return '#fff';
                        const r = parseInt(m[1],16), g = parseInt(m[2],16), b = parseInt(m[3],16);
                        const yiq = ((r*299)+(g*587)+(b*114))/1000;
                        return yiq >= 128 ? '#000' : '#fff';
                    }catch(_){ return '#fff'; }
                }
                function badgeStatus(s){
                    const v = String(s||'').toLowerCase();
                    const color = STATUS_COLORS[v] || '#adb5bd';
                    const label = s ? titleCase(s) : '—';
                    const tc = textColorFor(color);
                    return `<span class="badge" style="background-color: ${color}; color: ${tc};">${label}</span>`;
                }

                function lineifyAddress(a){
                    if (!a) return ['N/A'];
                    if (typeof a === 'string') {
                        try {
                            const parsed = JSON.parse(a);
                            if (parsed && typeof parsed === 'object') a = parsed; else return [a];
                        } catch(_) { return [a]; }
                    }
                    try{
                        const title = a.title || null;
                        const street = a.street || a.address1 || a.address || null;
                        const city = a.city || null;
                        const state = (a.state && typeof a.state === 'object') ? a.state.name : (a.state || null);
                        const country = (a.country && typeof a.country === 'object') ? a.country.name : (a.country || null);
                        const pincode = a.pincode || a.postal_code || a.zip || null;
                        const cc = a.country_code || null;
                        const phone = a.phone || null;
                        const lines = [];
                        if (title) lines.push(String(title));
                        if (street) lines.push(String(street));
                        const cityState = [city, state].filter(Boolean).join(', ');
                        if (cityState) lines.push(cityState);
                        if (country) lines.push(String(country));
                        if (pincode) lines.push('Postal code: ' + pincode);
                        if (phone) lines.push('Phone: ' + (cc ? ('+'+cc+' ') : '') + phone);
                        return lines.length ? lines : ['N/A'];
                    }catch(_){
                        return ['N/A'];
                    }
                }

                function renderOrder(o){
                    const p = o.payment_status ?? '—';
                    const pBadge = badgePayment(p);

                    // Build items list (sorted by item status priority, then by name)
                    const ITEM_STATUS_ORDER = [
                        'pending','processing','warehouse packing','from supplier','shipped','in transit to zim','dropped at the deport','out for delivery','ready for collection','delivered','stuck','cancelled','canceled'
                    ];
                    const RANK = Object.fromEntries(ITEM_STATUS_ORDER.map((s,i)=>[s,i]));
                    const itemsArr = (o.order_items || o.items || []).slice().sort((a,b)=>{
                        const sa = String(a.status || '').toLowerCase();
                        const sb = String(b.status || '').toLowerCase();
                        const ra = (RANK[sa] !== undefined) ? RANK[sa] : 999;
                        const rb = (RANK[sb] !== undefined) ? RANK[sb] : 999;
                        if (ra !== rb) return ra - rb;
                        return String(a.name || a.sku || '').localeCompare(String(b.name || b.sku || ''));
                    });
                    const items = itemsArr.map(it => {
                        const img = it.product_thumbnail_url ? `<img src="${it.product_thumbnail_url}" alt="${it.name || ''}" class="rounded" style="width:56px;height:56px;object-fit:cover;">` : `<div class=\"bg-body-secondary rounded d-flex align-items-center justify-content-center\" style=\"width:56px;height:56px;\">N/A</div>`;
                        const subtotal = (it.subtotal !== null && it.subtotal !== undefined) ? Number(it.subtotal) : (Number(it.quantity||0) * Number(it.single_price||0));
                        // Build variation line (from variation_name / variation_attributes) if available
                        let variationText = '';
                        try {
                            if (it.variation_display) {
                                variationText = String(it.variation_display);
                            } else {
                                const attrs = Array.isArray(it.variation_attributes) ? it.variation_attributes : [];
                                const vals = attrs.map(v => (typeof v === 'object' && v) ? (v.value || v.name || '') : (v || '')).filter(Boolean);
                                variationText = it.variation_name || (vals.length ? vals.join(', ') : '');
                            }
                        } catch(_) {}
                        const variationLine = variationText ? `<div class=\"mt-1\"><span class=\"badge text-bg-primary text-white\">${variationText}</span></div>` : '';

                        // Estimated delivery text
                        const estimatedDelivery = it.estimated_delivery_text ? `<div class=\"mt-1\"><span class=\"badge bg-info text-white\"><i class=\"bi bi-clock\"></i> ${it.estimated_delivery_text}</span></div>` : '';

                        return `
                            <div class=\"list-group-item px-0\">
                              <div class=\"d-flex gap-3\">
                                ${img}
                                <div class=\"flex-fill\">
                                  <div class=\"d-flex justify-content-between align-items-start\">
                                    <div>
                                      <div class=\"fw-semibold\">${it.name || ('SKU: ' + (it.sku || ''))}</div>
                                      ${variationLine}
                                      ${estimatedDelivery}
                                      <div class=\"small text-muted\">SKU: ${it.sku || ''}</div>
                                      <div class=\"small text-muted\">${(it.status && it.status !== '--') ? ('Status: ' + it.status) : ''}${it.eta ? ((it.status && it.status !== '--') ? ' • ' : '') + 'ETA: ' + (typeof it.eta === 'string' ? it.eta : safeDate(it.eta)) : ''}</div>
                                    </div>
                                    <div class=\"text-end\">
                                      <div class=\"fw-semibold\">${(o.currency_symbol || o.currency || '$')}${(((subtotal||0) * Number(o.exchange_rate || 1))).toFixed(2)}</div>
                                      <div class=\"small text-muted\">Qty: ${it.quantity || 1}${(it.single_price !== null && it.single_price !== undefined) ? (' × ' + (o.currency_symbol || o.currency || '$') + (Number(it.single_price||0) * Number(o.exchange_rate||1)).toFixed(2)) : ''}</div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>`;
                    }).join('');

                    // Addresses
                    const saLines = lineifyAddress(o.shipping_address || o.address || null);
                    const baLines = lineifyAddress(o.billing_address || null);

                    // History
                    let histories = [];
                    if (Array.isArray(o.order_history)) {
                        histories = o.order_history;
                    } else if (typeof o.order_history === 'string' && o.order_history.trim().length) {
                        try { const parsed = JSON.parse(o.order_history); if (Array.isArray(parsed)) histories = parsed; } catch(_) {}
                    }
                    if (!histories.length) {
                        if (Array.isArray(o.status_histories)) histories = o.status_histories;
                        else if (typeof o.status_histories === 'string' && o.status_histories.trim().length) {
                            try { const parsed = JSON.parse(o.status_histories); if (Array.isArray(parsed)) histories = parsed; } catch(_) {}
                        }
                    }
                    const historyHtml = histories.map(h => {
                        const oldName = (h.old_status && h.old_status.name) ? h.old_status.name : (h.old_status || null);
                        const newName = (h.new_status && h.new_status.name) ? h.new_status.name : (h.new_status || null);
                        const byName  = (h.updated_by && h.updated_by.name) ? h.updated_by.name : (h.updated_by || null);
                        const at      = h.created_at || h.updated_at || null;
                        return `
                            <div class=\"list-group-item mb-1\">
                              <div class=\"d-flex justify-content-between align-items-center\">
                                <div>
                                  ${badgeStatus(oldName)} <span class=\"mx-1\">→</span> ${badgeStatus(newName)}
                                  <span class=\"text-muted small\">by ${byName || '—'}</span>
                                </div>
                                <div class=\"text-muted small\">${safeDate(at)}</div>
                              </div>
                            </div>`;
                    }).join('');

                    // Sections
                    const currentStatusId = (o.order_status_id !== undefined && o.order_status_id !== null) ? o.order_status_id : (o.order_status && o.order_status.id ? o.order_status.id : null);
                    const statusName = (o.order_status && o.order_status.name) ? o.order_status.name : (o.status_text || o.status || '');
                    const dp = Number(o.delivery_price || 0);
                    const norm = (v) => String(v || '').trim().toLowerCase();
                    const filteredStatuses = (ORDER_STATUSES || []).filter(s => {
                        const name = norm(s.name);
                        if (dp > 0) {
                            // If delivery price > 0, exclude "ready for collection"
                            return name !== 'ready for collection';
                        } else {
                            // If delivery price <= 0, exclude "delivered" and "out for delivery"
                            return name !== 'delivered' && name !== 'out for delivery';
                        }
                    });
                    const statusOptions = filteredStatuses.map(s => `<option value=\"${s.id}\" ${Number(currentStatusId)===Number(s.id)?'selected':''}>${s.name}</option>`).join('');
                    const statusEditorHtml = `
                        <div class=\"card mb-3\">\n                              <div class=\"card-header py-2\"><div class=\"fw-semibold\">Update Order Status</div></div>\n                              <div class=\"card-body\">\n                                <div class=\"d-flex gap-2 align-items-center\">\n                                  <select id=\"orderStatusSelect\" class=\"form-select\" style=\"max-width:280px;\">${statusOptions}</select>\n                                  <button id=\"saveOrderStatusBtn\" class=\"btn btn-primary\" data-order-id=\"${o.id}\" data-order-number=\"${o.order_number ?? ''}\">Save</button>\n                                  <span id=\"orderStatusSaveSpinner\" class=\"spinner-border spinner-border-sm\" role=\"status\" aria-hidden=\"true\" style=\"display:none;\"></span>\n                                </div>\n                                <div class=\"small text-muted mt-1\">This will update the order status in this app and in the API.</div>\n                              </div>\n                            </div>`;
                    const detailsHtml = `
                        <div class=\"mb-3\">
                          <div class=\"d-flex align-items-center justify-content-between\">
                            <div>
                              <div class=\"fw-semibold\">Order #${o.order_number ?? o.id}</div>
                              <div class=\"text-muted small\">${safeDate(o.created_at)}</div>
                            </div>
                            <div class=\"d-flex gap-2 align-items-center\">${badgeStatus(statusName)} </div>
                          </div>
                        </div>

                        ${statusEditorHtml}

                        <div class=\"row g-2\">
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Order #</div>
                            <div class=\"fw-semibold\">${o.order_number ?? o.id ?? '-'}</div>
                          </div>
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Currency</div>
                            <div class=\"fw-semibold\">${o.currency ?? '-'}</div>
                          </div>

                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Consumer Name</div>
                            <div class=\"fw-semibold\">${o.consumer_name ?? '-'}</div>
                          </div>
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Consumer Email</div>
                            <div class=\"fw-semibold\">${o.consumer_email ?? '-'}</div>
                          </div>

                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Consumer Phone</div>
                            <div class=\"fw-semibold\">${(o.consumer_country_code ? ('+'+o.consumer_country_code+' ') : '') + (o.consumer_phone_number ?? '-')}</div>
                          </div>
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Subtotal</div>
                            <div class=\"fw-semibold\">${money((Number(o.subtotal||0) * Number(o.exchange_rate||1)), (o.currency_symbol || o.currency))}</div>
                          </div>

                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Discount</div>
                            <div class=\"fw-semibold\">${money((Number(o.discount_total||0) * Number(o.exchange_rate||1)), (o.currency_symbol || o.currency))}</div>
                          </div>
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Tax</div>
                            <div class=\"fw-semibold\">${money((Number(o.tax_total||0) * Number(o.exchange_rate||1)), (o.currency_symbol || o.currency))}</div>
                          </div>

                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Shipping</div>
                            <div class=\"fw-semibold\">${money((Number(o.shipping_total||0) * Number(o.exchange_rate||1)), (o.currency_symbol || o.currency))}</div>
                          </div>
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Delivery Price</div>
                            <div class=\"fw-semibold\">${money((Number(o.delivery_price||0) * Number(o.exchange_rate||1)), (o.currency_symbol || o.currency))}</div>
                          </div>

                          ${Number(o.payfast_fee||0) > 0 ? `
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">EFT / PayFast Fee (3%)</div>
                            <div class=\"fw-semibold text-danger\">${money((Number(o.payfast_fee||0) * Number(o.exchange_rate||1)), (o.currency_symbol || o.currency))}</div>
                          </div>
                          <div class=\"col-6\"></div>
                          ` : ''}

                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Amount</div>
                            <div class=\"fw-semibold\">${money((Number(o.amount||0) * Number(o.exchange_rate||1)), (o.currency_symbol || o.currency))}</div>
                          </div>
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Total</div>
                            <div class=\"fw-semibold\">${money((Number(o.total||0) * Number(o.exchange_rate||1)), (o.currency_symbol || o.currency))}</div>
                          </div>

                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Payment Method</div>
                            <div class=\"fw-semibold\">${o.payment_method === 'cod' ? 'Payment at the office' : o.payment_method ?? '-'}</div>
                          </div>
                          <div class=\"col-6\">
                            <div class=\"small text-muted\">Payment Status</div>
                            <div class=\"fw-semibold\">${o.payment_status ?? '-'}</div>
                          </div>

                          <div class=\"col-12\">
                            <div class=\"small text-muted\">Delivery Method</div>
                            <div class=\"fw-semibold\">${o.delivery_description ?? '-'}</div>
                          </div>
                        </div>`;

                    const addressHtml = `
                        <div class=\"row g-3\">
                          <div class=\"col-md-6\">
                            <div class=\"card\">
                              <div class=\"card-header py-2\"><div class=\"fw-semibold\">Shipping Address</div></div>
                              <div class=\"card-body\">${saLines.map(l=>`<div class=\\"small text-muted\\">${typeof l==='string' ? l : JSON.stringify(l)}</div>`).join('')}</div>
                            </div>
                          </div>
                          <div class=\"col-md-6\">
                            <div class=\"card\">
                              <div class=\"card-header py-2\"><div class=\"fw-semibold\">Billing Address</div></div>
                              <div class=\"card-body\">${baLines.map(l=>`<div class=\\"small text-muted\\">${typeof l==='string' ? l : JSON.stringify(l)}</div>`).join('')}</div>
                            </div>
                          </div>
                        </div>`;

                    const itemsHtml = `<div class=\"list-group list-group-flush\">${items || '<div class=\\"text-muted small\\">No items.</div>'}</div>`;

                    const historySection = historyHtml ? ('<div class=\\"list-group list-group-flush\\">'+historyHtml+'</div>') : '<div class=\\"text-muted small\\">No status history.</div>';

                    // Tabs wrapper with colored tab buttons and unstyled cards for content
                    const wrap = (c) => `<div class=\"card border-0 shadow-sm\"><div class=\"card-body\">${c}</div></div>`;

                    body.innerHTML = `
                      <ul class=\"nav nav-tabs drawer-tabs\">
                        <li class=\"nav-item\"><a href=\"#\" class=\"nav-link active\" data-tab-target=\"details\">Order Details</a></li>
                        <li class=\"nav-item\"><a href=\"#\" class=\"nav-link\" data-tab-target=\"address\">Address</a></li>
                        <li class=\"nav-item\"><a href=\"#\" class=\"nav-link\" data-tab-target=\"items\">Order Items</a></li>
                        <li class=\"nav-item\"><a href=\"#\" class=\"nav-link\" data-tab-target=\"history\">Order History</a></li>
                      </ul>
                      <div class=\"tab-content pt-3\">
                        <div class=\"tab-pane fade drawer-tab-pane show active\" data-tab=\"details\">${wrap(detailsHtml)}</div>
                        <div class=\"tab-pane fade drawer-tab-pane\" data-tab=\"address\">${wrap(addressHtml)}</div>
                        <div class=\"tab-pane fade drawer-tab-pane\" data-tab=\"items\">${wrap(itemsHtml)}</div>
                        <div class=\"tab-pane fade drawer-tab-pane\" data-tab=\"history\">${wrap(historySection)}</div>
                      </div>`;
                }
            })();
        </script>
    @endpush

    @push('scripts')
        <script>
            (function(){
                const form = document.getElementById('ordersSearchForm');
                if (!form) return;
                const input = form.querySelector('input[name="search"]');
                const statusSel = form.querySelector('select[name="status"]');
                let t;
                input?.addEventListener('input', () => {
                    clearTimeout(t);
                    t = setTimeout(() => form.requestSubmit(), 3000);
                });
                statusSel?.addEventListener('change', () => form.requestSubmit());
            })();
        </script>
    @endpush

    @push('scripts')
        <!-- QR and PDF libraries -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdn.jsdelivr.net/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>
            function addressLines(addr){
                if (!addr) return ['N/A'];
                try{
                    if (typeof addr === 'string') { try{ addr = JSON.parse(addr); }catch(_){} }
                    const title = addr.title || null;
                    const street = addr.street || addr.address || addr.address1 || null;
                    const city = addr.city || null;
                    const state = (addr.state && typeof addr.state === 'object') ? addr.state.name : (addr.state || null);
                    const pincode = addr.pincode || addr.postal_code || addr.zip || null;
                    const country = (addr.country && typeof addr.country === 'object') ? addr.country.name : (addr.country || null);
                    const cc = addr.country_code || null;
                    const phone = addr.phone || null;
                    const lines = [];
                    if (title) lines.push(String(title));
                    if (street) lines.push(String(street));
                    lines.push([city, state].filter(Boolean).join(', ')).filter(Boolean);
                    if (country) lines.push(String(country));
                    if (pincode) lines.push('Postal: ' + pincode);
                    if (phone) lines.push('Phone: ' + (cc ? ('+'+cc+' ') : '') + phone);
                    return lines.filter(Boolean);
                }catch(_){ return ['N/A']; }
            }

            function makeWaybillElement(o){
                const orderNo = o.order_number || o.id;
                const name = o.consumer_name || '—';
                const cell = ((o.consumer_country_code ? ('+'+o.consumer_country_code+' ') : '') + (o.consumer_phone_number || '—'));
                const sa = addressLines(o.shipping_address || o.address || {});
                const id = String(o.id);
                const el = document.createElement('div');
                el.id = 'waybill-'+id;
                el.style.width = '800px';
                el.style.maxWidth = '100%';
                el.style.background = '#fff';
                el.style.padding = '16px';
                el.style.border = '1px solid #e5e7eb';
                el.innerHTML = `
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div>
                                <div style="font-size:22px; font-weight:700;">Raines Africa — Waybill</div>
                                <div style="color:#6b7280;">Order #${orderNo}</div>
                            </div>
                            <div id="wb-qr-${id}"></div>
                        </div>
                        <div style="display:flex; gap:16px;">
                            <div style="flex:1;">
                                <div style="font-weight:600; margin-bottom:4px;">Customer</div>
                                <div>${name}</div>
                                <div>${cell}</div>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:600; margin-bottom:4px;">Shipping Address</div>
                                ${sa.map(l=>`<div>${String(l)}</div>`).join('')}
                            </div>
                        </div>
                        <div style="margin-top:16px;">
                            <svg id="wb-barcode-${id}"></svg>
                        </div>
                    `;
                return el;
            }

            async function fetchOrder(id){
                const url = `{{ route('app.orders.show', ['order' => '___ID___']) }}`.replace('___ID___', String(id));
                const res = await fetch(url, { headers: { 'Accept':'application/json' } });
                if (!res.ok) throw new Error('Failed to load order');
                return await res.json();
            }

            function genCodes(el, o){
                const id = String(o.id);
                try {
                    const qrEl = el.querySelector('#wb-qr-'+id);
                    const payload = {
                        order_number: o.order_number || o.id,
                        customer_name: o.consumer_name || null,
                        cell: (o.consumer_country_code ? ('+'+o.consumer_country_code+' ') : '') + (o.consumer_phone_number || ''),
                        shipping_address: addressLines(o.shipping_address || o.address || {})
                    };
                    new QRCode(qrEl, { text: JSON.stringify(payload), width: 120, height: 120 });
                } catch(_){ }
                try {
                    const svg = el.querySelector('#wb-barcode-'+id);
                    JsBarcode(svg, String(o.order_number || o.id), { format:'CODE128', width:2, height:60, displayValue:true });
                } catch(_){ }
            }

            function printWaybill(o){

            }

            function downloadWaybill(o){
                const el = makeWaybillElement(o);
                // Append to DOM for accurate rendering
                document.body.appendChild(el);
                genCodes(el, o);
                const opt = { filename: `Waybill-${o.order_number || o.id}.pdf`, margin: 10, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };
                html2pdf().set(opt).from(el).save().then(()=>{ el.remove(); }).catch(()=>{ el.remove(); });
            }

            // Expose helpers for header buttons
            window.printCurrentWaybill = function(){ try { var o = window.CURRENT_ORDER; if(!o){ alert('Open an order first'); return; } printWaybill(o); } catch(e){ console.error(e); alert('Failed to generate waybill'); } };
            window.downloadCurrentWaybill = function(){ try { var o = window.CURRENT_ORDER; if(!o){ alert('Open an order first'); return; } downloadWaybill(o); } catch(e){ console.error(e); alert('Failed to generate waybill'); } };

            // Bind click handlers directly to drawer buttons
            window.bindWaybillButtons = function(){
                try {
                    var p = document.getElementById('drawerWaybillPrint');
                    var d = document.getElementById('drawerWaybillDownload');
                    if (p) { p.onclick = function(ev){ ev.preventDefault(); ev.stopPropagation(); window.printCurrentWaybill(); }; }
                    if (d) { d.onclick = function(ev){ ev.preventDefault(); ev.stopPropagation(); window.downloadCurrentWaybill(); }; }
                } catch(_) {}
            };

            // Delegate (fallback) in case buttons are re-rendered without onclick
            document.addEventListener('click', async (e) => {
                const printBtn = e.target.closest('#drawerWaybillPrint');
                const dlBtn = e.target.closest('#drawerWaybillDownload');
                if (printBtn || dlBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var o = window.CURRENT_ORDER;
                    if (!o) {
                        const id = (printBtn || dlBtn).getAttribute('data-order-id');
                        if (id) {
                            try { o = await fetchOrder(id); window.CURRENT_ORDER = o; } catch(_) {}
                        }
                    }
                    if (!o) { alert('Open an order first'); return; }
                    if (printBtn) printWaybill(o); else downloadWaybill(o);
                }
            });
        </script>
    @endpush


</div>
