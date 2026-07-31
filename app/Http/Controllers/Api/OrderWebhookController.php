<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Jobs\UpsertOrderFromWebhook;

class OrderWebhookController extends Controller
{
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
            Log::warning('Webhook signature mismatch',['expected'=>$expect,'given'=>$given,'header'=>$header,'secret'=>$secret]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $key = $request->header('X-Idempotency-Key');
        if (!$key) {
            Log::warning('Missing idempotency key',['key'=>$key]);
            return response()->json(['message' => 'Missing idempotency key'], 400);
        }
//        $already = DB::table('sync_events')->where('key', $key)->exists();

//        $already = DB::table('sync_events')->where('key', $key)->exists();
//        if ($already) {
//            Log::warning('Already processed',['already'=>$already]);
//            return response()->json(['message' => 'Already processed'], 200);
//        }

        //Log::info('three');

        //DB::table('sync_events')->insert(['key' => $key]);

        //Log::info('four');

        // Queue actual upsert work
        UpsertOrderFromWebhook::dispatch($request->input('order'), $request->input('event', 'updated'))->afterCommit();
        //Log::info('last');
        return response()->json(['ok' => true]);
    }
}
