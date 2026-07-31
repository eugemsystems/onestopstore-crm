<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfilesController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaleOrdersController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Livewire\Volt\Volt;

Route::redirect('/', '/login');

Route::group(['middleware' => ['auth','active'], 'as' => 'app.', 'prefix' => 'app'], function () {
        // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Activities
    Route::get('activities', [\App\Http\Controllers\ActivityController::class, 'index'])
        ->name('activities')
        ->middleware('role:super-admin');

    //Users
    Route::get('users', [UsersController::class, 'users'])->name('users')->middleware('can:view user');
    Route::get('users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit')->middleware('can:view user,update user');
    Route::get('users/{user}', [UsersController::class, 'show'])->name('users.show')->middleware('can:view user');
    Route::get('roles', [RolesController::class, 'roles'])->name('roles')->middleware('can:view role');
    Route::get('permissions', [UsersController::class, 'permissions'])->name('permissions')->middleware('can:view permission');

    //profile
    Route::get('profile', [ProfilesController::class, 'profile'])->name('profile')
        ->middleware('can:view profile');

    //orders
    Route::get('orders', [OrderController::class, 'orders'] )->name('orders');
    Route::get('orders/items', [OrderController::class, 'ordersItems'] )->name('orders.items');
    Route::get('orders/stale-items', [StaleOrdersController::class, 'index'])->name('orders.stale-items');
    Route::get('orders/{order}', function (\App\Models\Order $order) {
        $order->load('order_items','order_status');
        $arr = $order->toArray();
        // expose a uniform 'items' key for the drawer JS
        $arr['items'] = array_map(function($it){
            return $it;
        }, $arr['order_items'] ?? []);
        return response()->json($arr);
    })->name('orders.show');
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');

    // Order item status management
    Route::put('orders/{order}/items/{orderProduct}/status', [\App\Http\Controllers\OrderItemStatusController::class, 'update'])
        ->name('orders.items.update-status');
    Route::put('orders/{order}/items/bulk-status', [\App\Http\Controllers\OrderItemStatusController::class, 'bulkUpdate'])
        ->name('orders.items.bulk-update-status');

     // TEST ROUTE - Remove after testing
    Route::get('test-eta-sync', function() {
        return view('test-eta-sync');
    });
    Route::post('test-js-working', function() {
        file_put_contents(storage_path('logs/test-js-working.txt'), date('Y-m-d H:i:s') . ' - JavaScript is loading and making requests' . PHP_EOL, FILE_APPEND);
        return response()->json(['ok' => true]);
    });

    // Mark order as viewed
    Route::post('orders/{order}/viewed', function (\App\Models\Order $order, Request $request) {
        try {
            $order->is_viewed = true;
            $order->save();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    })->name('orders.mark-viewed');

    // Analytics Dashboard
    Route::get('analytics', [\App\Http\Controllers\AnalyticsDashboardController::class, 'index'])->name('analytics.dashboard');
});

//only auth routes
Route::group(['middleware' => ['auth','active']], function () {
    Route::view('/activation-pending', 'auth.activation-pending')->name('activation.notice');
    Route::post('/notifications/mark-all-read', function () {
        auth()->user()->unreadNotifications->markAsRead();
        \Cache::forget('user_notifications_' . auth()->id());
        return back();
    })->name('notifications.markAllRead');

    Route::post('/notifications/{notification}/mark-read', function ($notificationId) {
        $n = auth()->user()->notifications()->findOrFail($notificationId);
        $n->markAsRead();
        \Cache::forget('user_notifications_' . auth()->id());
        return back();
    })->name('notifications.markRead');

    Route::get('/app/notifications', [NotificationsController::class, 'index'])->name('notifications.index');

    // Analytics API endpoints
    Route::get('/analytics/active-users', [\App\Http\Controllers\AnalyticsDashboardController::class, 'activeUsers'])->name('analytics.active-users');
    Route::get('/analytics/cart-abandonments', [\App\Http\Controllers\AnalyticsDashboardController::class, 'cartAbandonments'])->name('analytics.cart-abandonments');
    Route::get('/analytics/sessions/{sessionId}', [\App\Http\Controllers\AnalyticsDashboardController::class, 'sessionDetails'])->name('analytics.session-details');
});

Route::group(['middleware' => ['auth','role:super-admin','active']], function () {
    Route::get('/app/settings', [SettingController::class, 'index'])->name('app.settings');

    Route::post('/app/users/{user}/activate', [\App\Http\Controllers\UsersController::class, 'activate'])
        ->name('users.activate')
        ->middleware(['auth','permission:update user']); // adjust to your gate

    //cache clear with  artisan commands
    Route::get('/optimize-clear', function () {
        Artisan::call('optimize:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
        //Artisan::call('permission:cache');
        return "Cache is cleared";
    });

    Route::get('/optimize', function () {
        Artisan::call('optimize');
        Artisan::call('route:cache');
        Artisan::call('config:cache');
        Artisan::call('view:cache');
        return "Cache is OPTIMIZED";
    });

    Route::get('/storage-link', function () {
        Artisan::call('storage:link');
        return "Storage link created successfully";
    });

});

require __DIR__.'/auth.php';
