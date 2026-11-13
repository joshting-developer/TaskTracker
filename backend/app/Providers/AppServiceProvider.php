<?php

namespace App\Providers;

use App\Database\WorkspaceSqliteConnection;
use App\Events\MailDeliveryFailed;
use App\Http\Middleware\GuardedStartSession;
use App\Listeners\RecordMailDeliveryFailure;
use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * 註冊無法自動解析的容器綁定與啟動設定；一般concrete class直接由Container自動解析。
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(StartSession::class, GuardedStartSession::class);
        Connection::resolverFor('sqlite', fn ($pdo, $database, $prefix, $config) => new WorkspaceSqliteConnection($pdo, $database, $prefix, $config));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Event::listen(MailDeliveryFailed::class, RecordMailDeliveryFailure::class);
        RateLimiter::for('personal-token-create', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->getAuthIdentifier() ?? 'guest:'.$request->ip()));
        RateLimiter::for('personal-token-revoke', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?? 'guest:'.$request->ip()));
        RateLimiter::for('public-api-v1', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Sanctum::authenticateAccessTokensUsing(fn ($token, $valid) => $valid && $token->revoked_at === null);
    }
}
