<?php

namespace App\Notifications;

use App\Models\OrderItemMessage;
use App\Models\OrderProduct;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Notifications\TelegramChannel;

class NewOrderItemMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public OrderItemMessage $message;
    public ?OrderProduct $item = null;
    public ?User $sender = null;

    public function __construct(OrderItemMessage $message)
    {
        $this->message = $message;
        try {
            $this->item = OrderProduct::with('order:id,order_number')
                ->find($message->order_product_id);
        } catch (\Throwable) {
            $this->item = null;
        }
        try {
            $this->sender = User::find($message->user_id);
        } catch (\Throwable) {
            $this->sender = null;
        }
    }

    public function via(object $notifiable): array
    {
        // Database always
        $channels = ['database'];

        // Telegram gated by settings + token + user chat
        $sendTelegram = (bool) getCachedSetting('send_telegram');
        $hasToken = (string) config('services.telegram.bot_token', '') !== '';
        $hasChat = method_exists($notifiable, 'routeNotificationForTelegram') && $notifiable->routeNotificationForTelegram($this) !== null;
        if ($sendTelegram && $hasToken && $hasChat) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $orderId = $this->item?->order_id;
        $orderNo = $this->item?->order?->order_number;
        $itemName = $this->item?->name;
        $sku = $this->item?->sku;
        $senderName = $this->sender?->full_name ?? trim(($this->sender->first_name ?? '') . ' ' . ($this->sender->last_name ?? '')) ?: 'User';
        $preview = Str::limit(trim(strip_tags((string) $this->message->body)), 140);

        // We can deep-link to items page; optionally include a hint param for the UI to focus the chat
        $url = route('app.orders.items') . '?chat=' . (int)($this->item?->id ?? 0);

        return [
            'type' => 'order_item_message',
            'title' => 'You were mentioned in an item message Order#'.$orderNo,
            'message' => 'New message on Order#'.$orderNo.' product: '.$itemName.' from '.($senderName),
            'message_id' => (int) $this->message->id,
            'order_product_id' => (int) ($this->item?->id ?? 0),
            'order_id' => $orderId ? (int) $orderId : null,
            'order_number' => $orderNo,
            'item_name' => $itemName,
            'sku' => $sku,
            'by' => [
                'id' => (int) ($this->sender->id ?? 0),
                'name' => $senderName,
            ],
            'body_preview' => $preview,
            'url' => $url,
        ];
    }

    public function toTelegram(object $notifiable): array
    {
        $orderNo = $this->item?->order?->order_number ?? ($this->item?->order_id ?? '');
        $itemName = trim((string)($this->item?->name ?? 'Item'));
        $sender = $this->sender?->full_name ?? trim(($this->sender->first_name ?? '') . ' ' . ($this->sender->last_name ?? '')) ?: 'User';
        $preview = Str::limit(trim(strip_tags((string) $this->message->body)), 200);
        $url = route('app.orders.items') . '?chat=' . (int)($this->item?->id ?? 0);

        // Escape for HTML parse mode to avoid formatting issues
        $esc = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $itemNameE = $esc($itemName);
        $senderE = $esc($sender);
        $previewE = $esc($preview);

        $text = "You were mentioned in an item message\n".
            "Order #{$orderNo}\n".
            "Product: {$itemNameE}\n".
            "From: {$senderE}\n\n".
            $previewE;

        $payload = [
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($this->isValidTelegramButtonUrl($url)) {
            $payload['extra'] = [
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [ [ 'text' => 'Open in app', 'url' => $url ] ]
                    ]
                ]),
            ];
        }

        return $payload;
    }

    protected function isValidTelegramButtonUrl(string $url): bool
    {
        if (trim($url) === '') return false;
        $parts = parse_url($url);
        if (!$parts || !isset($parts['scheme'], $parts['host'])) return false;
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return false;
        $host = strtolower($parts['host']);
        if ($host === 'localhost' || $host === '127.0.0.1') return false;
        return true;
    }
}
