<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
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
        // Implicitly grant 'super_admin' role all permissions
        // Checks using can() / Gate::allows() return true for super_admin
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
