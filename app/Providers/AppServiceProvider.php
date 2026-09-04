<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Gate for super admin access
        Gate::define('super-admin', function ($user) {
            return $user->isSuperAdmin();
        });

        // Limite de tentativas de login, por IP. Resolvido a cada requisicao
        // (e nao no momento em que a rota e definida), para que o valor nao
        // seja congelado por um route:cache gerado em outro ambiente.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(
            config('auth.login_max_attempts')
        )->by($request->ip()));
    }
}
