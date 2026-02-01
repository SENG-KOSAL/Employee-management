<?php

namespace App\Providers;

use App\Models\Employee;
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
        Gate::define('create-user', function (User $user): bool {
            return $user->isAdmin();
        });

        Gate::define('approve-leave-for', function (User $user, Employee $employee): bool {
            if ($user->isAdmin() || $user->isHr()) {
                return true;
            }

            if (! $user->isManager()) {
                return false;
            }

            // Manager can approve only for their direct reports.
            return $user->employee_id !== null
                && (int) $employee->line_manager_id === (int) $user->employee_id;
        });
    }
}
