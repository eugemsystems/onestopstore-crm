<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $subject ?? (config('app.name') . ' Order Notification') }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #374151; background-color: #f3f4f6; margin: 0; padding: 32px 16px; line-height: 1.6; }
        .container { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07); overflow: hidden; border: 1px solid #e5e7eb; }
        .header { display: flex; align-items: center; justify-content: center; padding: 24px; background: #1a1a2e; color: white; }
        .logo img { max-height: 50px; }
        .app-name { font-weight: 600; font-size: 18px; }
        .section { padding: 24px; border-bottom: 1px solid #e5e7eb; }
        .card { background: #ffffff; }
        .muted { color: #6b7280; font-size: 14px; }
        .title { font-size: 18px; font-weight: 600; margin-bottom: 12px; color: #111827; }
        .subtitle { font-size: 16px; font-weight: 500; color: #374151; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 12px;}
        th, td { padding: 12px; text-align: left; }
        thead th { background: #f9fafb; font-weight: 500; color: #374151; border-bottom: 2px solid #e5e7eb; }
        tbody tr { border-bottom: 1px solid #e5e7eb; }
        tbody tr:last-child { border-bottom: none; }
        .right { text-align: right; }
        .detail-row { display: table; width: 100%; border-bottom: 1px solid #f3f4f6; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { display: table-cell; width: 40%; padding: 10px 12px; font-size: 13px; color: #6b7280; font-weight: 500; vertical-align: middle; }
        .detail-value { display: table-cell; width: 60%; padding: 10px 12px; font-size: 13px; font-weight: 600; color: #111827; text-align: right; vertical-align: middle; word-break: break-word; }
        .address-box { background: #f9fafb; border-radius: 6px; padding: 12px; margin-top: 8px; font-size: 14px; line-height: 1.5; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { padding: 8px 0; border-bottom: 1px dashed #e5e7eb; }
        .summary-table tr:last-child td { border-bottom: none; font-weight: 600; font-size: 16px; color: #111827; padding-top: 12px; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 100px; font-size: 13px; font-weight: 500; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-badge-status { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .footer { padding: 24px; text-align: center; background: #f9fafb; color: #6b7280; font-size: 14px; }
        @media (max-width: 640px) { .detail-item { flex-direction: column; } .detail-label, .detail-value { flex: 1; text-align: left; } .detail-value { margin-top: 4px; } .header { flex-direction: column; gap: 12px; text-align: center; } }
    </style>
</head>
<body>
@php
    $rate = (float) ($order->exchange_rate ?? 1);
    $symbol = $order->currency_symbol ?? ($order->currency ?? '$');

    $itemsSubtotalBase = (float) ($order->subtotal ?? $order->amount ?? 0.0);
    if ($itemsSubtotalBase <= 0 && isset($items)) {
        try {
            $itemsSubtotalBase = collect($items)->sum(function($it){
                $qty = (int) ($it->quantity ?? 1);
                $base = (isset($it->sale_price) && (float)$it->sale_price > 0)
                    ? (float) $it->sale_price
                    : (float) ($it->price ?? $it->single_price ?? 0);
                $qty = $qty > 0 ? $qty : 1;
                return $base * $qty;
            });
        } catch (\Throwable $e) { /* ignore */ }
    }
    $shippingBase      = (float) ($order->shipping_total ?? 0.0);
    $deliveryBase      = (float) ($order->delivery_price ?? 0.0);
    $taxBase           = (float) ($order->tax_total ?? 0.0);
    $discountBase      = (float) ($order->discount_total ?? $order->coupon_total_discount ?? 0.0);
    $grandBase         = (float) ($order->total ?? 0.0);

    $itemsSubtotal = $itemsSubtotalBase * $rate;
    $shipping      = $shippingBase * $rate;
    $delivery      = $deliveryBase * $rate;
    $tax           = $taxBase * $rate;
    $discount      = $discountBase * $rate;
    $grand         = $grandBase > 0 ? ($grandBase * $rate) : ($itemsSubtotal + $shipping + $delivery + $tax - $discount);

    // Determine status badge class
    $ps = strtoupper((string)($order->payment_status ?? ''));
    $paymentStatusClass = 'status-pending';
    if ($ps === 'COMPLETED') { $paymentStatusClass = 'status-paid'; }
    elseif ($ps === 'CANCELLED') { $paymentStatusClass = 'status-failed'; }
@endphp

<div class="container">
    <div class="header">
        <div class="logo">
            <img src="https://media.raines.africa/storage/uploads/2025/07/24/b35706e8-980f-4c6c-a87d-b0a24e6378fd.png" alt="{{ config('app.name') }}" />
        </div>
    </div>

    <!-- Message Section -->
    <div class="section card">
        @php
            $orderNo = $order->order_number ?? $order->id;
            $deliveryDesc = (string) ($order->delivery_description ?? '');
            $isCollection = stripos($deliveryDesc, 'Free Collection') !== false;
            $branch = null;
            if ($isCollection) {
                if (preg_match('/Free\s*Collection\s*-\s*([^—\-]+)/i', $deliveryDesc, $m)) {
                    $branch = trim($m[1]);
                }
            }
            $orderStatusText = strtolower(trim((string) (
                data_get($order, 'order_status.name')
                ?: data_get($order, 'order_status')
                ?: ($order->order_status ?? $order->status ?? $order->status_text ?? '')
            )));

            $isDelivery = (float)($order->delivery_price ?? 0) > 0;
            $itemsHasReady = collect($items ?? [])->contains(fn($it) => strtolower(trim((string)($it->status ?? ''))) === 'ready for collection');
            $itemsHasOut   = collect($items ?? [])->contains(fn($it) => strtolower(trim((string)($it->status ?? ''))) === 'out for delivery');

            $showReadyCollectionBlock = (!$isDelivery) && (($orderStatusText === 'ready for collection') || $itemsHasReady) && !empty($branch);
            $showOutForDeliveryBlock  = $isDelivery && (($orderStatusText === 'out for delivery') || $itemsHasOut);

            $deadline = now()->addDays(7)->format('j F Y');
        @endphp

        @if($showReadyCollectionBlock)
            <div class="title">Order Ready for Collection</div>
            @if(!empty($greeting))
                <p>{{ $greeting }}</p>
            @endif
            <p>
                Your Raines Africa Order # {{ $orderNo }} is ready for collection at our {{ $branch }} Pickup point.
                You have until {{ $deadline }} to collect your order.
            </p>
            <p>
                Scan the QR Code at the collection point when collecting your order.
            </p>
            @if(!empty($qrCodeData))
                <div style="text-align:center; margin: 16px 0;">
                    <img src="{{ $qrCodeData }}" alt="Collection QR Code" width="180" height="180" style="border: 6px solid #f3f4f6; border-radius: 12px; display: block; margin: 0 auto;" />
                </div>
            @endif
        @elseif($showOutForDeliveryBlock)
            <div class="title">Out for Delivery</div>
            @if(!empty($greeting))
                <p>{{ $greeting }}</p>
            @endif
            <p>Your order # {{ $orderNo }} will be delivered today before 5pm.</p>
            <p>Items to be delivered today are listed below:</p>
            @if(!empty($qrCodeData))
                <div style="text-align:center; margin: 16px 0;">
                    <img src="{{ $qrCodeData }}" alt="Delivery QR Code" width="180" height="180" style="border: 6px solid #f3f4f6; border-radius: 12px; display: block; margin: 0 auto;" />
                </div>
            @endif
        @else
            <div class="title">{{ $heading ?? 'Order Notification' }}</div>
            @if(!empty($greeting))
                <p>{{ $greeting }}</p>
            @endif
            @if(!empty($intro))
                <div class="mb-2">{!! nl2br(e($intro)) !!}</div>
            @else
                <p>Thank you for your order. We are processing it and will notify you once it's on its way.</p>
            @endif

            @if(!empty($statusLines) && is_array($statusLines) && count($statusLines) > 1)
                @foreach($statusLines as $line)
                    <p>{{ $line }}</p>
                @endforeach
            @endif

            @if(!empty($showSplitNote))
                <div style="background:#fef3c7;border-left:4px solid #f59e0b;border-radius:6px;padding:12px 16px;margin-top:8px;font-size:13px;color:#92400e;">
                    ⚠ Items have different ETA dates and will be delivered in separate shipments.
                </div>
            @endif
        @endif
    </div>

    <!-- Order details -->
    <div class="section card">
        <div class="title">Order Information</div>
        <div class="muted">Placed on {{ optional($order->created_at)->format('F j, Y \a\t g:i A') }}</div>

        <div style="background:#f9fafb;border-radius:10px;border-left:4px solid #C0392B;margin-top:16px;overflow:hidden;">

            <div class="detail-row">
                <span class="detail-label">Order Number</span>
                <span class="detail-value">#{{ $order->order_number ?? $order->id }}</span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Order Status</span>
                @php
                    $__raw = (
                        data_get($order, 'order_status.name')
                        ?: data_get($order, 'order_status')
                        ?: ($order->order_status ?? $order->status ?? $order->status_text ?? '')
                    );
                    $__statusName = is_numeric($__raw) ? (\App\Models\OrderStatus::find((int)$__raw)->name ?? (string)$__raw) : (string)$__raw;
                    $__label = $__statusName !== '' ? ucwords($__statusName) : '—';
                    $__bg = \App\Helpers\OrderStatusColors::hex($__statusName);
                    $__tc = \App\Helpers\OrderStatusColors::textColor($__bg);
                @endphp
                <span class="detail-value">
                    <span class="status-badge-status" style="background: {{ $__bg }}; color: {{ $__tc }};">{{ $__label }}</span>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Payment Status</span>
                <span class="detail-value">
                    <span class="status-badge {{ $paymentStatusClass }}">{{ $order->payment_status }}</span>
                </span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Payment Method</span>
                @php
                    $pmCode = (string)($order->payment_method ?? '');
                    $pmLabel = $pmCode !== ''
                        ? (strtolower($pmCode) === 'cod'
                            ? 'Payment at the office'
                            : (function_exists('paymentMethodLabel') ? (paymentMethodLabel($pmCode) ?: strtoupper($pmCode)) : strtoupper($pmCode)))
                        : '-';
                @endphp
                <span class="detail-value">{{ $pmLabel }}</span>
            </div>

            @if(!empty($order->delivery_description))
            <div class="detail-row">
                <span class="detail-label">Delivery Method</span>
                <span class="detail-value">{{ $order->delivery_description }}</span>
            </div>
            @endif

            <div class="detail-row">
                <span class="detail-label">Total Amount</span>
                <span class="detail-value" style="color:#C0392B;font-size:15px;">{{ $symbol }} {{ number_format((float)$grand, 2, '.', ',') }}</span>
            </div>

            @if(!empty($order->note))
            <div class="detail-row">
                <span class="detail-label">Order Note</span>
                <span class="detail-value" style="color:#6b7280;">{{ $order->note }}</span>
            </div>
            @endif

        </div>
    </div>

    <!-- Address Information -->
    <div class="section card">
        <div class="title">Address Details</div>
        <table class="order-details-table">
            <tr>
                @if(!empty($order->billing_address))
                    <td width="50%" style="vertical-align: top;">
                        <div class="subtitle">Billing Address</div>
                        <div class="address-box">
                            {{ data_get($order, 'billing_address.title') }}<br>
                            @if(!empty(data_get($order, 'billing_address.phone')))
                                +{{ data_get($order, 'billing_address.country_code') }} {{ data_get($order, 'billing_address.phone') }}<br>
                            @endif
                            {{ data_get($order, 'billing_address.street') }}<br>
                            {{ data_get($order, 'billing_address.city') }}, {{ data_get($order, 'billing_address.state.name') }} {{ data_get($order, 'billing_address.pincode') }}<br>
                            {{ data_get($order, 'billing_address.country.name') }}
                        </div>
                    </td>
                @endif
                @if(!empty($order->shipping_address))
                    <td width="50%" style="vertical-align: top;">
                        <div class="subtitle">Shipping Address</div>
                        <div class="address-box">
                            {{ data_get($order, 'shipping_address.title') }}<br>
                            @if(!empty(data_get($order, 'shipping_address.phone')))
                                +{{ data_get($order, 'shipping_address.country_code') }} {{ data_get($order, 'shipping_address.phone') }}<br>
                            @endif
                            {{ data_get($order, 'shipping_address.street') }}<br>
                            {{ data_get($order, 'shipping_address.city') }}, {{ data_get($order, 'shipping_address.state.name') }} {{ data_get($order, 'shipping_address.pincode') }}<br>
                            {{ data_get($order, 'shipping_address.country.name') }}
                        </div>
                    </td>
                @endif
            </tr>
        </table>
    </div>

    <!-- Products/Items table -->
    <div class="section card">
        <div class="title">Order Items</div>
        <style>
            .product-item-row { border-bottom: 1px solid #e5e7eb; }
            .product-item-row:last-child { border-bottom: none; }
            .product-item-row.fast-shipping { background: #fffbeb; border-left: 3px solid #f59e0b; }
            .product-name { font-weight: 600; color: #111827; margin-bottom: 4px; font-size: 14px; }
            .variation-badges { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px; }
            .var-badge {
                display: inline-block;
                padding: 2px 8px;
                background: #dcfce7;
                color: #166534;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 500;
            }
            .fast-badge {
                display: inline-block;
                padding: 2px 8px;
                background: #fef3c7;
                color: #92400e;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
            }
            .item-meta { font-size: 11px; color: #6b7280; margin-top: 2px; }
        </style>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
            <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                <th style="padding: 12px; text-align: left; font-weight: 500; color: #374151;">Item</th>
                <th style="padding: 12px; text-align: right; font-weight: 500; color: #374151;">Price</th>
                <th style="padding: 12px; text-align: left; font-weight: 500; color: #374151;">{{ $updatedLabel ?? ($kind === 'eta' ? 'ETA' : ($kind === 'both' ? 'Status / ETA' : 'Status')) }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($items as $it)
                @php
                    $name = $it->name ?? (isset($it->sku) ? ('SKU: '.$it->sku) : 'Item');
                    $sku  = $it->sku ?? '-';
                    $qnt  = $it->quantity ?? 1;
                    $etaVal = isset($it->eta) && $it->eta ? ($it->eta instanceof \Carbon\Carbon ? $it->eta->format('Y-m-d') : (string)$it->eta) : '-';
                    $statusVal = isset($it->status) ? ucwords(str_replace('_',' ', (string)$it->status)) : '-';

                    // Get variation display name
                    $variationDisplayName = $it->variation_display_name ?? null;
                    $variationParts = $variationDisplayName ? explode(' - ', $variationDisplayName) : [];

                    // Check for fast shipping
                    $hasFastShipping = ($it->has_fast_shipping ?? false) || ($it->fast_shipping_cost ?? 0) > 0;
                    $fastShippingCost = (float)($it->fast_shipping_cost ?? 0);
                @endphp
                <tr class="product-item-row {{ $hasFastShipping ? 'fast-shipping' : '' }}">
                    <td style="padding: 12px;">
                        <div class="product-name">
                            {{ $name }}
                            @if($hasFastShipping)
                                <span style="color: #f59e0b; font-size: 16px;">⚡</span>
                            @endif
                        </div>

                        @if(!empty($variationParts))
                            <div class="variation-badges">
                                @foreach($variationParts as $part)
                                    <span class="var-badge">{{ trim($part) }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if($hasFastShipping)
                            <div style="margin-top: 4px;">
                                <span class="fast-badge">
                                    ⚡ Fast Shipping{{ $fastShippingCost > 0 ? ' (' . $symbol . number_format($fastShippingCost, 2) . ')' : '' }}
                                </span>
                            </div>
                        @endif

                        <div class="item-meta">
                            SKU: {{ $sku }} | Qty: {{ $qnt }}
                        </div>
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        @php
                            $rate = (float) ($order->exchange_rate ?? 1);
                            $basePrice = (float) ($it->price ?? $it->single_price ?? 0);
                            $salePrice = isset($it->sale_price) ? (float) $it->sale_price : null;
                        @endphp
                        @if(!is_null($salePrice) && $salePrice > 0)
                            <div style="color: #6b7280;"><del>{{ $symbol }} {{ number_format($basePrice * $rate, 2, '.', ',') }}</del></div>
                            <div style="color: #111827; font-weight: 600;">{{ $symbol }} {{ number_format($salePrice * $rate, 2, '.', ',') }}</div>
                        @elseif($basePrice > 0)
                            <div style="color: #111827; font-weight: 600;">{{ $symbol }} {{ number_format($basePrice * $rate, 2, '.', ',') }}</div>
                        @else
                            <div style="color: #6b7280;">-</div>
                        @endif
                    </td>
                    <td style="padding: 12px;">
                        @if(($kind ?? '') === 'eta')
                            <strong>{{ $etaVal }}</strong>
                        @elseif(($kind ?? '') === 'both')
                            @php
                                $__bg = \App\Helpers\OrderStatusColors::hex($statusVal);
                                $__tc = \App\Helpers\OrderStatusColors::textColor($__bg);
                            @endphp
                            <div>
                                <span class="status-badge-status" style="background: {{ $__bg }}; color: {{ $__tc }};">{{ $statusVal }}</span>
                                <div style="margin-top: 4px;"><small>ETA: <strong>{{ $etaVal }}</strong></small></div>
                            </div>
                        @else
                            @php
                                $__bg = \App\Helpers\OrderStatusColors::hex($statusVal);
                                $__tc = \App\Helpers\OrderStatusColors::textColor($__bg);
                            @endphp
                            <span class="status-badge-status" style="background: {{ $__bg }}; color: {{ $__tc }};">{{ $statusVal }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <!-- Order Summary - Now at the bottom -->
    <div class="section card">
        <div class="title">Order Summary</div>
        <table class="summary-table">
            <tr>
                <td>Items Subtotal:</td>
                <td class="right">{{ $symbol }} {{ number_format((float)$itemsSubtotal, 2, '.', ',') }} </td>
            </tr>
            <tr>
                <td>Shipping:</td>
                <td class="right">{{ $symbol }} {{ number_format((float)$shipping, 2, '.', ',') }} </td>
            </tr>
            <tr>
                <td>Delivery:</td>
                <td class="right">{{ $symbol }} {{ number_format((float)$delivery, 2, '.', ',') }} </td>
            </tr>
            <tr>
                <td>Tax:</td>
                <td class="right">{{ $symbol }} {{ number_format((float)$tax, 2, '.', ',') }} </td>
            </tr>
            <tr>
                <td>Discount:</td>
                <td class="right">- {{ $symbol }} {{ number_format((float)$discount, 2, '.', ',') }} </td>
            </tr>
            <tr>
                <td>Grand Total:</td>
                <td class="right">{{ $symbol }} {{ number_format((float)$grand, 2, '.', ',') }} </td>
            </tr>
        </table>
    </div>

    <div style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 24px;text-align:center;font-size:13px;color:#6b7280;">
        <p style="font-size:15px;font-weight:600;color:#1a1a2e;margin:0 0 6px;">{{ config('app.name') }}</p>
        <p style="margin:0 0 4px;">This is an automated email. Please do not reply directly to this message.</p>
        <p style="margin:0 0 12px;">Questions? Contact <a href="mailto:admin@raines.africa" style="color:#C0392B;text-decoration:none;">admin@raines.africa</a></p>
        <p style="margin:0;">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
