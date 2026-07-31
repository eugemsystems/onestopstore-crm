<?php

use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Helpers\OrderStatusColors;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $paginationTheme = 'bootstrap';

    public function with(): array
    {
        $items = OrderProduct::query()
            ->with(['order:order_number,order_status'])
            ->where('order_products.updated_at', '<=', now()->subDays(3))
            ->orderBy('order_products.updated_at', 'asc')
            ->paginate(20);

        return [
            'items' => $items,
        ];
    }
}; ?>

<div>
    <div class="content__boxed">
        <div class="content__wrap">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Stale Order Items (Updated 3+ days ago)</h5>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th style="width:160px;">Order #</th>
                                <th>Product</th>
                                <th style="width:200px;">Item Status</th>
                                <th style="width:200px;">Order Status</th>
                                <th style="width:180px;">Last Updated</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php
                                if (!isset($items)) {
                                    $items = OrderProduct::query()
                                        ->with(['order:order_number,order_status'])
                                        ->where('order_products.updated_at', '<=', now()->subDays(3))
                                        ->orderBy('order_products.updated_at', 'asc')
                                        ->paginate(20);
                                }
                            @endphp
                            @forelse($items as $it)
                                @php
                                    $order = $it->order;
                                    $orderNo = $order?->order_number ?? $order?->id ?? '-';
                                    $itemStatus = (string) ($it->status ?? '');
                                    $itemStatusLabel = $itemStatus !== '' ? Str::title($itemStatus) : '—';
                                    $itemStatusBg = \App\Helpers\OrderStatusColors::hex($itemStatus);
                                    $itemStatusTc = \App\Helpers\OrderStatusColors::textColor($itemStatusBg);

                                    $orderStatusName = (string) (
                                        data_get($order, 'order_status.name')
                                        ?: data_get($order, 'order_status')
                                        ?: ($order?->order_status ?? $order?->status ?? $order?->status_text ?? '')
                                    );
                                    $orderStatusLabel = $orderStatusName !== '' ? Str::title($orderStatusName) : '—';
                                    $orderStatusBg = \App\Helpers\OrderStatusColors::hex($orderStatusName);
                                    $orderStatusTc = \App\Helpers\OrderStatusColors::textColor($orderStatusBg);
                                @endphp
                                <tr>
                                    <td><b>#{{ $orderNo }}</b></td>
                                    <td>
                                        <div class="d-flex">
                                            <img style="width:56px;height:56px;border-radius:8px;margin-right:10px;object-fit:cover;border: 1px solid #e9ecef;" src="{{ $it->product_thumbnail_url ?: asset('default.png') }}" alt="product">
                                            <div>
                                                <div class="fw-semibold">{{ $it->name ?? ('SKU: '.$it->sku) }}</div>
                                                <div class="text-muted small">SKU: {{ $it->sku ?? '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: {{ $itemStatusBg }}; color: {{ $itemStatusTc }};">{{ $itemStatusLabel }}</span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: {{ $orderStatusBg }}; color: {{ $orderStatusTc }};">{{ $orderStatusLabel }}</span>
                                    </td>
                                    <td>{{ optional($it->updated_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted text-center">No items older than 3 days.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-center">
                        {{ $items->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
