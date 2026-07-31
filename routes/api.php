<?php

use App\Http\Controllers\Api\OrderWebhookController;
use App\Http\Controllers\Api\OrderItemWebhookController;
use App\Http\Controllers\OrderStatusWebhookController;
use App\Http\Controllers\Api\OrderCheckController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/orders', [OrderWebhookController::class, 'handle']);// for update from admin orders status update
Route::post('/webhooks/order/statuses', [OrderStatusWebhookController::class, 'handle']);
Route::post('/webhooks/order/item-status', [OrderItemWebhookController::class, 'itemStatusUpdated']);
Route::post('/webhooks/order/ready-for-collection', [OrderItemWebhookController::class, 'readyForCollection']);

// Order existence check endpoint (requires authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Primary methods: Check by ORDER ID (since CRM uses API order IDs as PKs)
    Route::get('/orders/check-by-id/{orderId}', [OrderCheckController::class, 'checkExistsById']);
    Route::get('/orders/by-id/{orderId}', [OrderCheckController::class, 'showById']);

    // Backward compatibility: Check by ORDER NUMBER
    Route::get('/orders/check/{orderNumber}', [OrderCheckController::class, 'checkExists']);
    Route::get('/orders/{orderNumber}', [OrderCheckController::class, 'show']);
});

