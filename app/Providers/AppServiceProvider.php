<?php

namespace App\Providers;

use App\Contracts\LLMProviderInterface;
use App\Models\Budget;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Observers\WalletObserver;
use App\Policies\BudgetPolicy;
use App\Policies\RecurringTransactionPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\WalletPolicy;
use App\Services\LLM\OpenAIProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LLMProviderInterface::class, OpenAIProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        // Define Gates for permissions
        Gate::define('hard-reset-password', function (User $user) {
            return $user->is_superadmin || in_array('hard_reset_password', $user->permissions ?? []);
        });

        Gate::define('manage-users', function (User $user) {
            return $user->is_superadmin || in_array('manage_users', $user->permissions ?? []);
        });

        Gate::define('view-reports', function (User $user) {
            return $user->is_superadmin || in_array('view_reports', $user->permissions ?? []);
        });

        Gate::define('manage-roles', function (User $user) {
            return $user->is_superadmin;
        });

        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(RecurringTransaction::class, RecurringTransactionPolicy::class);

        // Balance sync handled by UpdateWalletBalance listener (TransactionCreated/Updated/Deleted events)
        Wallet::observe(WalletObserver::class);
    }
}
