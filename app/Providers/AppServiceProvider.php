<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (glob(app_path('Helpers/*.php')) as $filename) {
            require_once $filename;
        }

        // Force HTTPS only in development environments
        if ($this->app->environment('local')) {
            URL::forceScheme('https');
        }

        // Register observers
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);

        //if the domain is not localhost or

//        if(request()->getHost() != 'localhost') {
//            //force https
//            URL::forceScheme('https');
//        }

        Paginator::useBootstrapFive();

        // Global Activity Logging for auth and model events
        if (config('activitylog.enabled')) {
            // Helper to capture request context
            $reqContext = function (): array {
                $req = request();
                $ip = $req->ip();
                $hostname = null;
                if ($ip) {
                    try {
                        $resolved = gethostbyaddr($ip);
                        if ($resolved && $resolved !== $ip) { $hostname = $resolved; }
                    } catch (\Throwable $e) { /* ignore */ }
                }
                return array_filter([
                    'ip' => $ip,
                    'x_forwarded_for' => $req->header('X-Forwarded-For'),
                    'user_agent' => $req->userAgent(),
                    'hostname' => $hostname,
                    'computer_name' => $req->header('X-Computer-Name') ?? $req->header('X-Client-Hostname') ?? $req->header('X-Device-Name'),
                    'client_mac' => $req->header('X-Client-MAC') ?? $req->header('X-Device-MAC'),
                    'client_hints' => array_filter([
                        'sec_ch_ua' => $req->header('Sec-CH-UA'),
                        'sec_ch_ua_platform' => $req->header('Sec-CH-UA-Platform'),
                        'sec_ch_ua_model' => $req->header('Sec-CH-UA-Model'),
                    ]),
                    'url' => $req->fullUrl(),
                ], function ($v) { return $v !== null && $v !== ''; });
            };

            // Auth events
            Event::listen(Login::class, function (Login $event) use ($reqContext) {
                activity('auth')
                    ->causedBy($event->user)
                    ->withProperties($reqContext())
                    ->event('login')
                    ->log('User logged in');
            });

            Event::listen(Logout::class, function (Logout $event) use ($reqContext) {
                $user = $event->user;
                activity('auth')
                    ->causedBy($user)
                    ->withProperties($reqContext())
                    ->event('logout')
                    ->log('User logged out');
            });

            Event::listen(Failed::class, function (Failed $event) use ($reqContext) {
                activity('auth')
                    ->byAnonymous()
                    ->withProperties(array_merge([
                        'email' => $event->credentials['email'] ?? null,
                    ], $reqContext()))
                    ->event('login_failed')
                    ->log('Authentication failed');
            });

            // Skip models that shouldn't be logged to avoid recursion/noise
            $shouldSkip = function ($model): bool {
                if (app()->runningInConsole()) { return true; }
                return $model instanceof Activity
                    || $model instanceof \App\Models\OrderItemMessage
                    || $model instanceof \App\Models\OrderItemMessageLike
                    || $model instanceof \App\Models\OrderItemMessageView
                    || $model instanceof \App\Models\OrderItemMessageMention;
            };

                Event::listen('eloquent.created: *', function ($eventName, array $data) use ($shouldSkip, $reqContext) {
                $model = $data[0] ?? null;
                if (!($model instanceof Model) || $shouldSkip($model)) { return; }
                activity('model')
                    ->performedOn($model)
                    ->event('created')
                    ->withProperties(array_merge([
                        'attributes' => $model->getAttributes(),
                    ], $reqContext()))
                    ->log(class_basename($model) . ' created');
            });

            Event::listen('eloquent.updated: *', function ($eventName, array $data) use ($shouldSkip, $reqContext) {
                $model = $data[0] ?? null;
                if (!($model instanceof Model) || $shouldSkip($model)) { return; }
                activity('model')
                    ->performedOn($model)
                    ->event('updated')
                    ->withProperties(array_merge([
                        'attributes' => $model->getAttributes(),
                        'changes' => $model->getChanges(),
                        'original' => $model->getOriginal(),
                    ], $reqContext()))
                    ->log(class_basename($model) . ' updated');
            });

            Event::listen('eloquent.deleted: *', function ($eventName, array $data) use ($shouldSkip, $reqContext) {
                $model = $data[0] ?? null;
                if (!($model instanceof Model) || $shouldSkip($model)) { return; }
                activity('model')
                    ->performedOn($model)
                    ->event('deleted')
                    ->withProperties(array_merge([
                        'attributes' => $model->getAttributes(),
                    ], $reqContext()))
                    ->log(class_basename($model) . ' deleted');
            });

            Event::listen('eloquent.restored: *', function ($eventName, array $data) use ($shouldSkip, $reqContext) {
                $model = $data[0] ?? null;
                if (!($model instanceof Model) || $shouldSkip($model)) { return; }
                activity('model')
                    ->performedOn($model)
                    ->event('restored')
                    ->withProperties(array_merge([
                        'attributes' => $model->getAttributes(),
                    ], $reqContext()))
                    ->log(class_basename($model) . ' restored');
            });

            Event::listen('eloquent.forceDeleted: *', function ($eventName, array $data) use ($shouldSkip, $reqContext) {
                $model = $data[0] ?? null;
                if (!($model instanceof Model) || $shouldSkip($model)) { return; }
                activity('model')
                    ->performedOn($model)
                    ->event('force_deleted')
                    ->withProperties(array_merge([
                        'attributes' => $model->getAttributes(),
                    ], $reqContext()))
                    ->log(class_basename($model) . ' force deleted');
            });
        }
    }
}
