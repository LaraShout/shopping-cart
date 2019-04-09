<?php

namespace LaraShout\ShoppingCart\Services;

use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Events\Dispatcher;

class Session
{
    protected $session;

    protected $event;

    protected $name = 'cart.session';

    protected $model;

    public function __construct(SessionManager $session, Dispatcher $event)
    {
        $this->session = $session;

        $this->event = $event;
    }

    public function name($name)
    {
        $this->name = 'cart.'.$name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function all()
    {
        return $this->getCart();
    }

    protected function getCart()
    {
        $cart = $this->session->get($this->name);

        return $cart instanceof Collection ? $cart : new Collection();
    }
}