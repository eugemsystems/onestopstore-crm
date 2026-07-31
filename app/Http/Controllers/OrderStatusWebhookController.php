<?php

namespace App\Http\Controllers;

use App\Jobs\UpsertOrderStatusFromWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderStatusWebhookController extends Controller
{
    //
    public function handle(Request $request)
    {

        $raw     = $request->getContent();
        $secret  = config('services.api_webhook.secret');
        $header  = $request->header('X-Signature', '');
        if (!str_starts_with($header, 'sha256=')) {
            return response()->json(['message' => 'Bad signature'], 400);
        }
        $given   = substr($header, 7);
        $expect  = hash_hmac('sha256', $raw, $secret);

        //Log::warning('Web Hook reached',['expected'=>$expect,'given'=>$given,'header'=>$header,'secret'=>$secret]);

        if (!hash_equals($expect, $given)) {
            Log::warning('Order Status Webhook signature mismatch',['expected'=>$expect,'given'=>$given,'header'=>$header,'secret'=>$secret]);
            return response()->json(['message' => 'Order Status Invalid signature'], 403);
        }

        //Log::info('one');

        $key = $request->header('X-Idempotency-Key');
            Log::warning('Order Status Missing idempotency key',['key'=>$key]);
        if (!$key) {
            return response()->json(['message' => 'Order Status Missing idempotency key'], 400);
        }

        // Queue actual upsert work
        UpsertOrderStatusFromWebhook::dispatch($request->input('orderstatus'), $request->input('event', 'updated'))->afterCommit();

        return response()->json(['ok' => true]);
    }
}
