<?php

/**
 * Test script to verify Order Items API sync
 * Run with: php artisan tinker
 * Then paste this code
 */

// Test 1: Verify OrdersApi service is available
try {
    $ordersApi = app(\App\Services\OrdersApi::class);
    echo "✅ OrdersApi service loaded successfully\n";
} catch (\Throwable $e) {
    echo "❌ Failed to load OrdersApi: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Find a test order with order_number
$testOrder = \App\Models\Order::whereNotNull('order_number')->with('products')->first();
if (!$testOrder) {
    echo "❌ No orders found with order_number\n";
    exit;
}
echo "✅ Found test order: {$testOrder->order_number} (ID: {$testOrder->id})\n";

// Test 3: Get first order product
$testItem = $testOrder->products->first();
if (!$testItem) {
    echo "❌ Order has no products\n";
    exit;
}
echo "✅ Found test item: {$testItem->name} (Product ID: {$testItem->product_id})\n";

// Test 4: Test single item update (dry run - just check structure)
$testData = [
    'product_id' => $testItem->product_id,
    'variation_id' => $testItem->variation_id,
    'item_status' => $testItem->status ?? 'pending',
    'eta' => $testItem->eta ? ($testItem->eta instanceof \Carbon\Carbon ? $testItem->eta->format('Y-m-d') : $testItem->eta) : null,
];
echo "✅ Test data structure valid:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n";

// Test 5: Verify API configuration
$apiBaseUrl = config('services.api.base_url');
$apiToken = config('services.api.token');
if (empty($apiBaseUrl)) {
    echo "⚠️  WARNING: services.api.base_url not configured\n";
} else {
    echo "✅ API Base URL: {$apiBaseUrl}\n";
}
if (empty($apiToken)) {
    echo "⚠️  WARNING: services.api.token not configured\n";
} else {
    echo "✅ API Token configured (length: " . strlen($apiToken) . ")\n";
}

echo "\n--- Test Summary ---\n";
echo "OrdersApi service: ✅ Available\n";
echo "Test order found: ✅ {$testOrder->order_number}\n";
echo "Test item found: ✅ {$testItem->name}\n";
echo "API endpoint: " . ($apiBaseUrl ? "✅ Configured" : "❌ Not configured") . "\n";
echo "API token: " . ($apiToken ? "✅ Configured" : "❌ Not configured") . "\n";

echo "\n--- To Test Actual API Call ---\n";
echo "Run this command (replace with actual values):\n";
echo "\$ordersApi->updateItemStatus('{$testOrder->order_number}', [\n";
echo "    'product_id' => {$testItem->product_id},\n";
echo "    'variation_id' => " . ($testItem->variation_id ?? 'null') . ",\n";
echo "    'item_status' => 'pending',\n";
echo "    'eta' => '2025-11-15',\n";
echo "]);\n";

