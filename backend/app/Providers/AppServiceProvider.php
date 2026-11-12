<?php

namespace App\Providers;

use App\Database\WorkspaceSqliteConnection;
use App\Http\Middleware\GuardedStartSession;
use App\Models\PersonalAccessToken;
use Illuminate\Database\Connection;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StartSession::class, GuardedStartSession::class);
        Connection::resolverFor('sqlite', fn ($pdo, $database, $prefix, $config) => new WorkspaceSqliteConnection($pdo, $database, $prefix, $config));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Sanctum::authenticateAccessTokensUsing(fn ($token, $valid) => $valid && $token->revoked_at === null);
    }
}
