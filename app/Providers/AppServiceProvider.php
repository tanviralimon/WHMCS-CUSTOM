<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        Vite::prefetch(concurrency: 3);

        // OAuth / OpenID Connect scopes
        Passport::tokensCan([
            'openid'  => 'Authenticate your identity',
            'profile' => 'Access your name and profile information',
            'email'   => 'Access your email address',
        ]);

        Passport::setDefaultScope(['openid', 'profile', 'email']);

        // Tokens expire in 15 days, refresh tokens in 30 days
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }
}
