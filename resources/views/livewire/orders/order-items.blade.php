<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $selectedId = null;

    public bool $drawerOpen = false;

    public string $paginationTheme = 'bootstrap';
    public array $queryString = ['search' => ['except' => '']];

    public function with(): array
    {
        $q = Order::query()->withCount('items')->latest('created_at');

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

        return [
            'orders'   => $q->paginate(2),
            'selected' => $this->selectedId ? Order::with('items')->find($this->selectedId) : null,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->drawerOpen = true;
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
    }

}; ?>


<div>
    @section('title','Order Items')
    @section('main_content')
        <style>
            .drawer-overlay { background: rgba(0,0,0,.35); }
            .drawer-panel { width: 420px; max-width: 100vw; transition: transform .25s ease; }
        </style>

        <div>
            <div class="content__header content__boxed overlapping">
                <div class="content__wrap">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item "><a href="{{route('app.dashboard')}}">Dashboard</a></li>
                            <li class="breadcrumb-item" aria-current="page"><a href="{{route('app.orders')}}">Orders</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Order Items</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="content__boxed">
                <div class="content__wrap">
                    <div class="card" bis_skin_checked="1">
                        <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between" bis_skin_checked="1">
                            <h5 class="card-title mb-0">Order Items</h5>

                            <form id="ordersSearchForm" method="GET" class="d-flex gap-2 align-items-center">
                                <div class="form-group mb-0" bis_skin_checked="1">
                                    <input type="text" name="search" placeholder="Search by #, status, currency, consumer..." class="form-control" autocomplete="off" value="{{ request('search','') }}">
                                </div>
                            </form>
                        </div>

                        <div class="card-body" bis_skin_checked="1">
                            <div class="table-responsive" bis_skin_checked="1">
                                table
                            </div>

                            <div class="mt-3 d-flex justify-content-center">
                                pagination
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer overlay -->
            <div id="orderDrawerOverlay" class="position-fixed top-0 start-0 w-100 h-100 drawer-overlay" style="z-index:1050; {{ $drawerOpen ? '' : 'display:none;' }}" wire:click="closeDrawer"></div>

            <!-- Drawer panel -->
            <div id="orderDrawerPanel" class="position-fixed top-0 end-0 h-100 bg-white shadow drawer-panel" style="z-index:1051; transform: translateX({{ $drawerOpen ? '0' : '100%' }});">
                <div class="d-flex align-items-center justify-content-between border-bottom px-3 py-3">
                    <h6 class="mb-0">Order Details</h6>
                    <button id="orderDrawerClose" class="btn btn-sm btn-light" wire:click="closeDrawer" aria-label="Close"><i class="demo-pli-cross"></i></button>
                </div>

                <div id="orderDrawerBody" class="p-3 overflow-auto h-100" style="padding-bottom: 6rem;">
                    @if($selected)
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-semibold">Order #{{ $selected->order_number ?? $selected->id }}</div>
                                    <div class="text-muted small">{{ $selected->created_at?->format('M d, Y H:i') ?? '-' }}</div>
                                </div>
                                <div>
                                    @php($p = $selected->payment_status)
                                    @switch($p)
                                        @case('paid')
                                            <span class="badge bg-success">Paid</span>
                                            @break
                                        @case('refunded')
                                            <span class="badge bg-danger">Refunded</span>
                                            @break
                                        @case('unpaid')
                                            <span class="badge bg-warning">Unpaid</span>
                                            @break
                                        @default
                                            <span class="badge bg-info-subtle text-info">{{ $p ?? '—' }}</span>
                                    @endswitch
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="small text-muted">Consumer</div>
                                <div>#{{ $selected->consumer_id }}</div>
                            </div>
                            <div class="col-6 text-end">
                                <div class="small text-muted">Currency</div>
                                <div>{{ $selected->currency ?? '-' }}</div>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted">Subtotal</div>
                                @php($v = $selected->subtotal)
                                <div>{{ is_null($v) ? '-' : ($selected->currency ? $selected->currency.' ' : '$') . number_format((float)$v, 2) }}</div>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted">Discount</div>
                                @php($v = $selected->discount_total)
                                <div>{{ is_null($v) ? '-' : ($selected->currency ? $selected->currency.' ' : '$') . number_format((float)$v, 2) }}</div>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted">Tax</div>
                                @php($v = $selected->tax_total)
                                <div>{{ is_null($v) ? '-' : ($selected->currency ? $selected->currency.' ' : '$') . number_format((float)$v, 2) }}</div>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted">Shipping</div>
                                @php($v = $selected->shipping_total)
                                <div>{{ is_null($v) ? '-' : ($selected->currency ? $selected->currency.' ' : '$') . number_format((float)$v, 2) }}</div>
                            </div>
                            <div class="col-12">
                                <div class="small text-muted">Total</div>
                                @php($v = $selected->total ?? $selected->amount)
                                <div class="fs-5 fw-semibold">{{ is_null($v) ? '-' : ($selected->currency ? $selected->currency.' ' : '$') . number_format((float)$v, 2) }}</div>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-2 fw-semibold">Items</div>
                        <div class="list-group list-group-flush">
                            @forelse($selected->order_items as $it)
                                <x-order-item-card :item="$it" :order="$selected" />
                            @empty
                                <div class="text-muted small">No items.</div>
                            @endforelse
                        </div>

                        <hr>

                        <div class="mb-2 fw-semibold">Shipping</div>
                        <div class="small text-muted">{{ is_array($selected->shipping_address) ? json_encode($selected->shipping_address) : ($selected->shipping_address ?? 'N/A') }}</div>

                        <div class="mt-3 mb-2 fw-semibold">Billing</div>
                        <div class="small text-muted">{{ is_array($selected->billing_address) ? json_encode($selected->billing_address) : ($selected->billing_address ?? 'N/A') }}</div>
                    @else
                        <div class="text-muted">Select an order to see details.</div>
                    @endif
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
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
                        const tr = e.target.closest('tr[data-order-id]');
                        if (!tr) return;

                        const id = tr.getAttribute('data-order-id');
                        try {
                            const url = `{{ route('orders.show', ['order' => '___ID___']) }}`.replace('___ID___', id);
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            if (!res.ok) throw new Error('Failed to fetch order');
                            const order = await res.json();
                            renderOrder(order);
                            openDrawer();
                        } catch (err) {
                            console.error(err);
                        }
                    });

                    function money(v, cur){
                        if (v === null || v === undefined) return '-';
                        const n = Number(v);
                        return `${cur ? cur + ' ' : '$'}${n.toFixed(2)}`;
                    }

                    function renderOrder(o){
                        const p = o.payment_status ?? '—';
                        const badge = (p === 'paid') ? '<span class="badge bg-success">Paid</span>' : (p === 'refunded') ? '<span class="badge bg-danger">Refunded</span>' : (p === 'unpaid') ? '<span class="badge bg-warning">Unpaid</span>' : `<span class=\"badge bg-info-subtle text-info\">${p}</span>`;

                        const items = (o.items || []).map(it => {
                            const img = it.product_thumbnail_url ? `<img src=\"${it.product_thumbnail_url}\" alt=\"${it.name || ''}\" class=\"rounded\" style=\"width:56px;height:56px;object-fit:cover;\">` : `<div class=\"bg-body-secondary rounded d-flex align-items-center justify-content-center\" style=\"width:56px;height:56px;\">N/A</div>`;
                            const subtotal = (it.subtotal !== null && it.subtotal !== undefined) ? Number(it.subtotal) : (Number(it.quantity||0) * Number(it.single_price||0));
                            return `
            <div class=\"list-group-item px-0\">
              <div class=\"d-flex gap-3\">
                ${img}
                <div class=\"flex-fill\">
                  <div class=\"d-flex justify-content-between align-items-start\">
                    <div>
                      <div class=\"fw-semibold\">${it.name || ('SKU: ' + it.sku)}</div>
                      <div class=\"small text-muted\">SKU: ${it.sku}${it.variation_id ? ' • Var: ' + it.variation_id : ''}</div>
                    </div>
                    <div class=\"text-end\">
                      <div class=\"fw-semibold\">${o.currency || '$'} ${subtotal.toFixed(2)}</div>
                      <div class=\"small text-muted\">Qty: ${it.quantity || 1}${(it.single_price !== null && it.single_price !== undefined) ? (' × ' + (o.currency || '$') + Number(it.single_price).toFixed(2)) : ''}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>`;
                        }).join('');

                        body.innerHTML = `
          <div class=\"mb-3\">
            <div class=\"d-flex align-items-center justify-content-between\">
              <div>
                <div class=\"fw-semibold\">Order #${o.order_number ?? o.id}</div>
                <div class=\"text-muted small\">${o.created_at ?? '-'}</div>
              </div>
              <div>${badge}</div>
            </div>
          </div>

          <div class=\"row g-2 mb-3\">
            <div class=\"col-6\">
              <div class=\"small text-muted\">Consumer</div>
              <div>#${o.consumer_id}</div>
            </div>
            <div class=\"col-6 text-end\">
              <div class=\"small text-muted\">Currency</div>
              <div>${o.currency ?? '-'}</div>
            </div>
            <div class=\"col-6\">
              <div class=\"small text-muted\">Subtotal</div>
              <div>${money(o.subtotal, o.currency)}</div>
            </div>
            <div class=\"col-6\">
              <div class=\"small text-muted\">Discount</div>
              <div>${money(o.discount_total, o.currency)}</div>
            </div>
            <div class=\"col-6\">
              <div class=\"small text-muted\">Tax</div>
              <div>${money(o.tax_total, o.currency)}</div>
            </div>
            <div class=\"col-6\">
              <div class=\"small text-muted\">Shipping</div>
              <div>${money(o.shipping_total, o.currency)}</div>
            </div>
            <div class=\"col-12\">
              <div class=\"small text-muted\">Total</div>
              <div class=\"fs-5 fw-semibold\">${money(o.total ?? o.amount, o.currency)}</div>
            </div>
          </div>

          <hr>
          <div class=\"mb-2 fw-semibold\">Items</div>
          <div class=\"list-group list-group-flush\">${items || '<div class=\\\"text-muted small\\\">No items.</div>'}</div>

          <hr>
          <div class=\"mb-2 fw-semibold\">Shipping</div>
          <div class=\"small text-muted\">${(typeof o.shipping_address === 'object') ? JSON.stringify(o.shipping_address) : (o.shipping_address ?? 'N/A')}</div>

          <div class=\"mt-3 mb-2 fw-semibold\">Billing</div>
          <div class=\"small text-muted\">${(typeof o.billing_address === 'object') ? JSON.stringify(o.billing_address) : (o.billing_address ?? 'N/A')}</div>
        `;
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
                    let t;
                    input?.addEventListener('input', () => {
                        clearTimeout(t);
                        t = setTimeout(() => form.requestSubmit(), 400);
                    });
                })();
            </script>
        @endpush
    @endsection
</div>
