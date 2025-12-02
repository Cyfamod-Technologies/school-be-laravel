<?php

namespace App\Providers;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
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
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Grant dashboard-wide access to admin and super admin users.
        Gate::before(function ($user, string $ability) {
            if (! $user) {
                return null;
            }

            $role = strtolower((string) ($user->role ?? ''));
            if (in_array($role, ['admin', 'super_admin'], true) || $user->hasAnyRole(['admin', 'super_admin'])) {
                return true;
            }

            return null;
        });
    }
}
