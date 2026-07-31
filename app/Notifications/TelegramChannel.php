<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class TelegramChannel
{
    /**
     * Send the given notification via Telegram Bot API.
     *
     * The notification should implement toTelegram($notifiable) and return an array:
     * [
     *   'chat_id' => optional chat id (if omitted, we'll use routeNotificationFor('telegram')),
     *   'text' => 'Message text',
     *   'parse_mode' => 'HTML'|'MarkdownV2'|null,
     *   'disable_web_page_preview' => bool|null,
     *   'extra' => [ ... any additional sendMessage params ... ]
     * ]
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toTelegram')) {
            return;
        }

        // Respect global setting to disable Telegram sends
        if (!(bool) getCachedSetting('send_telegram')) {
            Log::debug('TelegramChannel: send_telegram disabled by settings');
            return;
        }

        $payload = $notification->toTelegram($notifiable) ?? [];

        $chatId = $notifiable->routeNotificationFor('telegram', $notification) ?? ($payload['chat_id'] ?? null);
        $text = $payload['text'] ?? null;

        if (!$chatId || !$text) {
            Log::warning('TelegramChannel: missing chat_id or text', [
                'notifiable' => is_object($notifiable) ? get_class($notifiable) : gettype($notifiable),
                'chat_id' => $chatId,
                'has_text' => $text !== null,
            ]);
            return; // essentials missing
        }

        $token = (string) config('services.telegram.bot_token', '');
        if ($token === '') {
            Log::warning('TelegramChannel: missing TELEGRAM_BOT_TOKEN');
            return; // bot not configured
        }

        $apiUrl = "https://api.telegram.org/bot{$token}/sendMessage";

        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => Arr::get($payload, 'parse_mode', 'HTML'),
            'disable_web_page_preview' => Arr::get($payload, 'disable_web_page_preview', true),
        ], (array) Arr::get($payload, 'extra', []));

        try {
            Log::debug('TelegramChannel: sending', [
                'chat_id' => $chatId,
                'has_text' => $text !== null,
            ]);
            $response = Http::timeout(15)->asForm()->post($apiUrl, $params);
            $ok = $response->ok();
            $body = $response->body();
            Log::debug('TelegramChannel: response', [
                'status' => $response->status(),
                'ok' => $ok,
                'body' => $body,
            ]);
        } catch (\Throwable $e) {
            Log::error('TelegramChannel: exception', [ 'error' => $e->getMessage() ]);
            report($e);
        }
    }
}
