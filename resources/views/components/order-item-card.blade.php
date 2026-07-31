@props(['item', 'order'])

@php
    $statusColors = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'out_of_stock' => 'danger',
        'refunded' => 'secondary',
    ];
    $statusColor = $statusColors[$item->item_status ?? 'pending'] ?? 'secondary';
    $statusLabel = str_replace('_', ' ', ucwords($item->item_status ?? 'pending'));
@endphp

<div class="list-group-item px-0">
    <div class="d-flex gap-3">
        @if($item->product_thumbnail_url)
            <img src="{{ $item->product_thumbnail_url }}" alt="{{ $item->name }}" class="rounded" style="width:64px;height:64px;object-fit:cover;">
        @else
            <div class="bg-body-secondary rounded d-flex align-items-center justify-content-center" style="width:64px;height:64px;">
                <i class="bi bi-box-seam"></i>
            </div>
        @endif

        <div class="flex-fill">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-fill">
                    <div class="fw-semibold">{{ $item->name ?? ('SKU: '.$item->sku) }}</div>
                    <div class="small text-muted">
                        SKU: {{ $item->sku ?? 'N/A' }}
                        @if($item->variation_display)
                            {{-- Split multi-attribute variations by " - " to show as separate badges --}}
                            @php
                                $variationParts = array_filter(array_map('trim', explode(' - ', $item->variation_display)));
                            @endphp
                            @foreach($variationParts as $part)
                                <span class="badge bg-success-subtle text-success ms-1">{{ $part }}</span>
                            @endforeach
                        @elseif($item->variation_id)
                            <span class="badge bg-secondary-subtle text-secondary ms-1">Var: {{ $item->variation_id }}</span>
                        @endif
                    </div>

                    @if($item->cancellation_reason)
                        <div class="alert alert-danger small mt-2 mb-0 py-1 px-2">
                            <strong>Reason:</strong> {{ $item->cancellation_reason }}
                        </div>
                    @endif

                    @if($item->eta)
                        <div class="small text-muted mt-1">
                            <i class="bi bi-calendar-event"></i> <strong>ETA:</strong> {{ \Carbon\Carbon::parse($item->eta)->format('M d, Y') }}
                        </div>
                    @endif
                </div>

                <div class="text-end ms-3">
                    @php($v = $item->subtotal ?? ($item->quantity * ($item->single_price ?? 0)))
                    <div class="fw-semibold">{{ $order->currency ?? '$' }} {{ number_format((float)$v, 2) }}</div>
                    <div class="small text-muted">
                        Qty: {{ $item->quantity ?? 1 }}
                        @if(!is_null($item->single_price))
                            × {{ $order->currency ?? '$' }}{{ number_format((float)$item->single_price, 2) }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap align-items-center">
                <!-- Item Status Badge -->
                <span class="badge bg-{{ $statusColor }} text-white">
                    {{ $statusLabel }}
                </span>

                <!-- Fast Shipping Badge - Only show if fast shipping is selected -->
                @if($item->has_fast_shipping && $item->fast_shipping_cost > 0)
                    <span class="badge bg-primary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-rocket-takeoff"></i>
                        Express Shipping ({{ $order->currency_symbol ?? '$' }}{{ number_format($item->fast_shipping_cost, 2) }})
                    </span>
                @endif

                <!-- Update Status Button -->
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary ms-auto"
                    data-bs-toggle="modal"
                    data-bs-target="#itemStatusModal{{ $item->id }}">
                    <i class="bi bi-pencil"></i> Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include the modal component -->
<x-order-item-status-modal :orderItem="$item" :orderId="$order->id" />
