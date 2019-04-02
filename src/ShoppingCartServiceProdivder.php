<?php

namespace LaraShout\ShoppingCart;

use Illuminate\Support\ServiceProvider;
use LaraShout\ShoppingCart\Services\ShoppingCart;

class ShoppingCartServiceProdivder extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('shoppingcart', function () {

            return new ShoppingCart();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
