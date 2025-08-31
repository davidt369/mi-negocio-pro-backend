<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UsersPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UsersPolicy::class,
        \App\Models\Categories::class => \App\Policies\CategoriesPolicy::class,
        \App\Models\Products::class => \App\Policies\ProductsPolicy::class,
        \App\Models\Sales::class => \App\Policies\SalesPolicy::class,
        \App\Models\SaleItems::class => \App\Policies\SaleItemsPolicy::class,
        \App\Models\Purchases::class => \App\Policies\PurchasesPolicy::class,
        \App\Models\PurchaseItems::class => \App\Policies\PurchaseItemsPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}