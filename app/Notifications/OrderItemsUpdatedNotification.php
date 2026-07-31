<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Notifications\TelegramChannel;

class OrderItemsUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * kind: 'eta' | 'status' | 'both'
     * @param Order $order
     * @param Collection|array $items
     * @param string $kind
     */
    public function __construct(public Order $order, public $items, public string $kind)
    {
        if (!$this->items instanceof Collection) {
            $this->items = collect($this->items);
        }
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Email enabled by default (send unless explicitly disabled)
        $sendEmailsSetting = getCachedSetting('send_emails');
        $sendEmails = ($sendEmailsSetting === null || $sendEmailsSetting === true || $sendEmailsSetting === '1' || $sendEmailsSetting === 1);


        if ($sendEmails) {
            $channels[] = 'mail';
        }

        // Telegram gated by settings + token + user chat
        $sendTelegram = (bool) getCachedSetting('send_telegram');
        $hasToken = (string) config('services.telegram.bot_token', '') !== '';
        //$hasChat = method_exists($notifiable, 'routeNotificationForTelegram') && $notifiable->routeNotificationForTelegram($this) !== null;
        if ($sendTelegram && $hasToken) {
            $channels[] = TelegramChannel::class;
        }


        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $orderNo = $this->order->order_number ?? $this->order->id;
        $items = $this->items->map(function ($it) {
            return [
                'id'     => $it->id ?? null,
                'sku'    => $it->sku ?? null,
                'name'   => $it->name ?? (isset($it->sku) ? ('SKU: '.$it->sku) : null),
                'eta'    => isset($it->eta) && $it->eta ? ($it->eta instanceof \Carbon\Carbon ? $it->eta->format('Y-m-d') : (string) $it->eta) : null,
                'status' => isset($it->status) ? (string) $it->status : null,
            ];
        })->values()->all();

        $etaGroups = in_array($this->kind, ['eta','both'], true)
            ? $this->items->map(fn($it) => $it->eta ? ($it->eta instanceof \Carbon\Carbon ? $it->eta->format('Y-m-d') : (string)$it->eta) : '-')
                ->unique()->values()->all()
            : [];

        $title = match ($this->kind) {
            'eta'    => 'ETA updated for order #'.$orderNo,
            'status' => 'Status updated for order #'.$orderNo,
            'both'   => 'Status and ETA updated for order #'.$orderNo,
            default  => 'Items updated for order #'.$orderNo,
        };

        $count = is_array($items) ? count($items) : 0;
        $message = match ($this->kind) {
            'eta'    => ($count > 1
                ? 'Estimated arrival dates were updated for '.$count.' items.'
                : 'Estimated arrival date was updated for '.$count.' item.'),
            'status' => ($count > 1
                ? 'Status was updated for '.$count.' items.'
                : 'Status was updated for '.$count.' item.'),
            'both'   => ($count > 1
                ? 'Status and ETA were updated for '.$count.' items.'
                : 'Status and ETA were updated for '.$count.' item.'),
            default  => ($count > 1 ? 'Items were updated.' : 'Item was updated.'),
        };

        return [
            'type'          => $this->kind,
            'order_id'      => $this->order->id,
            'order_number'  => $orderNo,
            'title'         => $title,
            'message'       => $message,
            'destination'   => $this->destinationCityCountry($this->order),
            'items'         => $items,
            'split_eta'     => in_array($this->kind, ['eta','both'], true) ? (count($etaGroups) > 1) : false,
            'consumer'      => [
                'name'          => $this->order->consumer_name,
                'email'         => $this->order->consumer_email,
                'phone'         => $this->formatConsumerPhone($this->order),
                'country_code'  => $this->order->consumer_country_code,
                'phone_number'  => $this->order->consumer_phone_number,
            ],
            'created_at'    => now()->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orderNo = $this->order->order_number ?? $this->order->id;

        // Out-of-stock special email path
        $outOfStockItems = $this->items->filter(function($it){
            $s = strtolower(trim((string)($it->status ?? '')));
            return $s === 'out of stock' || $s === 'out_of_stock' || $s === 'outofstock';
        });
        if ($outOfStockItems->count() > 0) {
            $name = trim((string)($this->order->consumer_name ?? 'Customer'));
            $dear = $name !== '' ? ("Dear {$name},") : 'Dear Customer,';

            $productLines = '';
            foreach ($outOfStockItems as $it) {
                $prod = (string)($it->name ?? ($it->sku ?? 'your product'));
                $productLines .= "We regret to inform you that your order for {$prod} cannot be fulfilled at this time. Despite our best efforts, both our warehouse and our suppliers no longer have stock available.\n\n";
            }
            $specialIntro = "Thank you for choosing Raines Africa.\n\n".
                $productLines.
                "You now have two options:\n".
                "• Select an alternative product from our range\n".
                "• Request a full refund to your bank account\n\n".
                "For your convenience, your Raines Africa Account has already been credited. You may use this credit immediately for another purchase, or request a payout to your bank at any time.\n\n".
                "We sincerely apologize for the inconvenience and thank you for your understanding. Our team is ready to assist you in finding the right replacement if needed.\n\n".
                "Kind regards,\n".
                "Raines Africa Customer Support";

            // Render via the same template, with custom subject/intro
            return (new MailMessage)
                ->subject('Important Update on Your Order – Raines Africa')
                ->view('emails.order-items-updated', [
                    'subject'       => 'Important Update on Your Order – Raines Africa',
                    'heading'       => 'Important Update',
                    'greeting'      => $dear,
                    'intro'         => $specialIntro,
                    'order'         => $this->order,
                    'items'         => $this->items,
                    'kind'          => 'status',
                    'consumer'      => [ 'email' => null, 'phone' => null ],
                    'showSplitNote' => false,
                    'currency'      => [ 'symbol' => $this->order->currency_symbol ?? null, 'code' => $this->order->currency ?? null ],
                    'statusLines'   => [],
                ]);
        }

        // Determine effective kind; if bulk update mistakenly sends 'status' while ETA provided, coerce to 'both'
        $hasAnyEta = $this->items->contains(function($it){ return isset($it->eta) && !empty($it->eta); });
        $effectiveKind = $this->kind;
        if ($this->kind === 'status' && $hasAnyEta) {
            $effectiveKind = 'both';
        }
        // Suppress ETA entirely when order is cancelled or any item is out of stock/cancelled
        $orderCancelled = false;
        try {
            // Use the correct order_status relationship
            $orderStatus = $this->order->order_status;
            if ($orderStatus) {
                $statusName = $orderStatus->name ?? $orderStatus->slug ?? '';
            } else {
                // Fallback if relationship not loaded
                $statusName = data_get($this->order, 'status_text', '');
            }
            $orderCancelled = in_array(strtolower(trim((string)$statusName)), ['cancelled','canceled']);
        } catch (\Throwable) {
            $orderCancelled = false;
        }
        $hasOutOfStock = false; $hasItemCancelled = false;
        try {
            $hasOutOfStock = $this->items->contains(function($it){
                $s = strtolower(trim((string)($it->status ?? '')));
                return in_array($s, ['out of stock','out_of_stock','outofstock']);
            });
            $hasItemCancelled = $this->items->contains(function($it){
                $s = strtolower(trim((string)($it->status ?? '')));
                return in_array($s, ['cancelled','canceled']);
            });
        } catch (\Throwable) {}
        $suppressEta = $orderCancelled || $hasOutOfStock || $hasItemCancelled;
        if ($suppressEta) { $effectiveKind = 'status'; }

        $subject = match ($effectiveKind) {
            'eta'    => 'ETA updated for your order #'.$orderNo,
            'status' => 'Status updated for your order #'.$orderNo,
            'both'   => 'Status and ETA updated for your order #'.$orderNo,
            default  => 'Items updated for your order #'.$orderNo,
        };

        $heading = match ($effectiveKind) {
            'eta'    => 'ETA Updated',
            'status' => 'Status Updated',
            'both'   => 'Status and ETA Updated',
            default  => 'Order Items Updated',
        };

        $intro = match ($effectiveKind) {
            'eta'    => 'We\'ve updated the estimated arrival date(s) for your items in order #'.$orderNo.'.',
            'status' => 'We\'ve updated the status of the following item(s) in order #'.$orderNo.'.',
            'both'   => 'We\'ve updated the status and estimated arrival date(s) for your item(s) in order #'.$orderNo.'.',
            default  => 'There are updates to your order items in order #'.$orderNo.'.',
        };

        $etaGroupsCount = 0;
        if (in_array($effectiveKind, ['eta','both'], true)) {
            $etaGroupsCount = $this->items->map(fn($it) => $it->eta ? ($it->eta instanceof \Carbon\Carbon ? $it->eta->format('Y-m-d') : (string)$it->eta) : '-')
                ->unique()->count();
        }
        $showSplitNote = in_array($effectiveKind, ['eta','both'], true) && $etaGroupsCount > 1;

        $currency = [
            'symbol' => $this->order->currency_symbol ?? null,
            'code'   => $this->order->currency ?? null,
        ];

        $consumer = [
            'email' => ($email = trim((string)($this->order->consumer_email ?? ''))) !== '' ? $email : null,
            'phone' => ($phone = $this->formatConsumerPhone($this->order)) !== '' ? $phone : null,
        ];

        // Build per-item combined narrative lines (status + ETA)
        $statusLines = [];
        try {
            $includeNames = ($this->items instanceof \Illuminate\Support\Collection ? $this->items->count() : count((array)$this->items)) > 1;
            // mirror suppression logic per-item lines
            $orderCancelled = false; $hasOutOfStock = false; $hasItemCancelled = false;
            try {
                // Use the correct order_status relationship
                $orderStatus = $this->order->order_status;
                if ($orderStatus) {
                    $statusName = $orderStatus->name ?? $orderStatus->slug ?? '';
                } else {
                    // Fallback if relationship not loaded
                    $statusName = data_get($this->order, 'status_text', '');
                }
                $orderCancelled = in_array(strtolower(trim((string)$statusName)), ['cancelled','canceled']);
                $hasOutOfStock = $this->items->contains(function($it){ $s=strtolower(trim((string)($it->status ?? ''))); return in_array($s,['out of stock','out_of_stock','outofstock']); });
                $hasItemCancelled = $this->items->contains(function($it){ $s=strtolower(trim((string)($it->status ?? ''))); return in_array($s,['cancelled','canceled']); });
            } catch (\Throwable) {}
            $suppressEta = $orderCancelled || $hasOutOfStock || $hasItemCancelled;
            foreach ($this->items as $it) {
                $status = isset($it->status) ? (string)$it->status : '';
                $eta    = isset($it->eta) && $it->eta ? ($it->eta instanceof \Carbon\Carbon ? $it->eta->format('j F Y') : (string)$it->eta) : null;
                $narr   = $this->narrativeForStatus($status, $this->order);
                if (!$suppressEta && $eta) {
                    $narr .= '. Estimated time of arrival is ' . $eta;
                }
                if ($includeNames) {
                    $name = (string)($it->name ?? ($it->sku ?? 'Item'));
                    $line = $name !== '' ? ($name . ': ' . $narr) : $narr;
                } else {
                    $line = $narr;
                }
                $statusLines[] = $line;
            }
        } catch (\Throwable) {}

        // For single item, replace intro with narrative
        if (count($statusLines) === 1) {
            $intro = $statusLines[0];
        }

        // Generate QR code if needed (for ready for collection or out for delivery)
        $qrCodeUrl = null;
        $qrCodePngData = null;
        try {
            $orderStatusText = strtolower(trim((string) (
                data_get($this->order, 'order_status.name')
                ?: data_get($this->order, 'order_status')
                ?: ($this->order->order_status ?? $this->order->status ?? $this->order->status_text ?? '')
            )));

            $isDelivery = (float)($this->order->delivery_price ?? 0) > 0;
            $itemsHasReady = $this->items->contains(fn($it) => strtolower(trim((string)($it->status ?? ''))) === 'ready for collection');
            $itemsHasOut   = $this->items->contains(fn($it) => strtolower(trim((string)($it->status ?? ''))) === 'out for delivery');

            $shouldGenerateQR = ((!$isDelivery) && (($orderStatusText === 'ready for collection') || $itemsHasReady))
                             || ($isDelivery && (($orderStatusText === 'out for delivery') || $itemsHasOut));

            if ($shouldGenerateQR) {
                // Generate QR code data - URL to collection/delivery scan endpoint
                $appUrl = rtrim(config('app.url'), '/');
                $qrDataUrl = $appUrl . '/admin/orders/collection-scan/' . $orderNo . '?auto_collect=true';

                // Generate QR code PNG - get raw string
                $qrCodePng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                    ->size(300)
                    ->margin(1)
                    ->errorCorrection('H')
                    ->generate($qrDataUrl);

                // Ensure it's a plain string, not HtmlString
                $qrCodePngData = (string) $qrCodePng;

                // Upload to laravel-media and get URL
                $qrCodeService = app(\App\Services\OrderQRCodeService::class);
                $qrType = $isDelivery ? 'delivery' : 'collection';
                $qrCodeUrl = $qrCodeService->generateAndSave($orderNo, $qrDataUrl, $qrType, $qrCodePngData);

                // If upload failed, use CID attachment
                if (!$qrCodeUrl) {
                    $qrCodeUrl = 'cid:order_qr_code';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to generate QR code for email', [
                'order_number' => $orderNo,
                'error' => $e->getMessage()
            ]);
        }

        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->view('emails.order-items-updated', [
                'subject'       => $subject,
                'heading'       => $heading,
                'greeting'      => $this->greeting(),
                'intro'         => $intro,
                'order'         => $this->order,
                'items'         => $this->items,
                'kind'          => $effectiveKind,
                'consumer'      => $consumer,
                'showSplitNote' => $showSplitNote,
                'currency'      => $currency,
                'statusLines'   => $statusLines,
                'qrCodeData'    => $qrCodeUrl,
            ]);

        // ALSO attach QR code as embedded image for better email client compatibility
        if ($qrCodePngData) {
            $mailMessage->withSymfonyMessage(function ($message) use ($qrCodePngData) {
                $message->embed($qrCodePngData, 'order_qr_code', 'image/png');
            });
        }

        return $mailMessage;
    }

    protected function greeting(): string
    {
        try {
            $name = trim((string)($this->order->consumer_name ?? ''));
            return $name !== '' ? "Hello {$name}," : 'Hello,';
        } catch (\Throwable) { return 'Hello,'; }
    }

    protected function formatConsumerPhone($order): string
    {
        try {
            $cc = trim((string)($order->consumer_country_code ?? ''));
            $ph = trim((string)($order->consumer_phone_number ?? ''));
            if ($ph === '') return '';
            if ($cc !== '') {
                $cc = ltrim($cc, '+');
                return '+'.$cc.' '.$ph;
            }
            return $ph;
        } catch (\Throwable) { return ''; }
    }

    protected function destinationCityCountry($order): string
    {
        try {
            $addr = $order->shipping_address ?? null;
            if (is_array($addr)) {
                $city = (string)($addr['city'] ?? '');
                $country = $addr['country'] ?? '';
                if (is_array($country)) $country = (string)($country['name'] ?? '');
                $country = (string)$country;
                $parts = array_filter([$city, $country], fn($v)=>trim((string)$v) !== '');
                return $parts ? implode(', ', $parts) : '-';
            }
            return '-';
        } catch (\Throwable) { return '-'; }
    }

    protected function narrativeForStatus(?string $status, $order): string
    {
        $s = trim(mb_strtolower((string)$status));
        $isDelivery = (float)($order->delivery_price ?? 0) > 0;
        return match ($s) {
            'from supplier'        => 'Your order is coming from the supplier',
            'warehouse packing'    => 'Our warehouse team is packing your product',
            'in transit to zim'    => 'Your order is in transit to Zimbabwe',
            'dropped at the deport'=> 'Your order has been dropped at the depot',
            'out for delivery'     => 'Your order is out for delivery',
            'ready for collection' => 'Your order is ready for collection',
            'delivered'            => 'Your order has been delivered',
            'processing'           => 'Your order is being processed',
            'pending'              => 'Your order is pending',
            'shipped'              => 'Your order has been shipped',
            'stuck'                => 'Your order is delayed',
            'cancelled', 'canceled'=> 'Your order has been cancelled',
            'collected'            => 'Your order has been collected',
            default                => ($s !== '' ? ('Your order status is '.ucwords($s)) : 'Your order has been updated'),
        };
    }

    public function toTelegram(object $notifiable): array
    {
        $orderNo = $this->order->order_number ?? $this->order->id;
        $items = $this->items instanceof \Illuminate\Support\Collection ? $this->items : collect($this->items);
        $title = match ($this->kind) {
            'eta'    => 'ETA updated',
            'status' => 'Status updated',
            'both'   => 'Status and ETA updated',
            default  => 'Items updated',
        };

        $lines = [];
        foreach ($items as $it) {
            $name = $it->name ?? ($it->sku ?? 'Item');
            if ($this->kind === 'eta') {
                $eta = isset($it->eta) && $it->eta ? ($it->eta instanceof \Carbon\Carbon ? $it->eta->format('Y-m-d') : (string)$it->eta) : '-';
                $lines[] = "• {$name} — ETA: {$eta}";
            } elseif ($this->kind === 'both') {
                $status = isset($it->status) ? (string) $it->status : '-';
                $eta    = isset($it->eta) && $it->eta ? ($it->eta instanceof \Carbon\Carbon ? $it->eta->format('Y-m-d') : (string)$it->eta) : '-';
                $lines[] = "• {$name} — Status: {$status}; ETA: {$eta}";
            } else {
                $status = isset($it->status) ? (string) $it->status : '-';
                $lines[] = "• {$name} — Status: {$status}";
            }
        }

        $text = $title." for order #{$orderNo}\n".
            implode("\n", array_slice($lines, 0, 10));

        return [
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
    }
}
