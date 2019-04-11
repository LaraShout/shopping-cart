<?php

namespace LaraShout\ShoppingCart\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Events\Dispatcher;
use LaraShout\ShoppingCart\Collection\Item;

class Session
{
    /**
     * @var SessionManager
     */
    protected $session;

    /**
     * @var Dispatcher
     */
    protected $event;

    /**
     * @var string
     */
    protected $name = 'cart.session';

    /**
     * @var $model
     */
    protected $model;

    /**
     * Session constructor.
     *
     * @param SessionManager $session
     * @param Dispatcher $event
     */
    public function __construct(SessionManager $session, Dispatcher $event)
    {
        $this->session = $session;

        $this->event = $event;
    }

    /**
     * Set the name of Shopping Cart
     *
     * @param $name
     * @return $this
     */
    public function name($name)
    {
        $this->name = 'cart.'.$name;
        return $this;
    }

    /**
     *  Get the name of Shopping Cart
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     *  Associate as Eloquent Model to Shopping Cart
     *
     * @param $model
     * @return $this
     * @throws Exception
     */
    public function associate($model)
    {
        if (!class_exists($model)) {
            throw new Exception("Invalid model name '$model'.");
        }
        $this->model = $model;
        return $this;
    }

    /**
     *  Get the associated Eloquent Model attached with Shopping Cart
     *
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     *  Get the row from Shopping Cart
     *
     * @param $rawId
     * @return Item|null
     */
    public function get($rawId)
    {
        $row = $this->getCart()->get($rawId);

        return is_null($row) ? null : new Item($row);
    }

    /**
     *  Get all items in Shopping Cart
     *
     * @return Collection
     */
    public function all()
    {
        return $this->getCart();
    }

    /**
     *  Returns the current Shopping Cart based on Session
     *
     * @return Collection
     */
    protected function getCart()
    {
        $cart = $this->session->get($this->name);

        return $cart instanceof Collection ? $cart : new Collection();
    }

    /**
     *  Generate a Raw Id for Item rows (__raw_id)
     *
     * @param $id
     * @param $attributes
     * @return string
     */
    protected function generateRawId($id, $attributes)
    {
        ksort($attributes);

        return md5($id.serialize($attributes));
    }

    /**
     *  Add an Item to Shopping Cart
     *
     * @param $id
     * @param $name
     * @param $qty
     * @param $price
     * @param $attributes
     * @return bool|mixed
     * @throws Exception
     */
    public function add($id, $name = null, $qty = null, $price = null, array $attributes = [])
    {
        $cart = $this->getCart();

        $this->event->push('cart.adding', [$attributes, $cart]);

        $row = $this->addRow($id, $name, $qty, $price, $attributes);

        $this->event->push('cart.added', [$attributes, $cart]);

        return $row;
    }

    /**
     *  Update an Item in Shopping Cart
     *
     * @param $rawId
     * @param $attribute
     * @return bool|mixed
     * @throws Exception
     */
    public function update($rawId, $attribute)
    {
        if (!$row = $this->get($rawId))
        {
            throw new Exception('Item not found.');
        }

        $cart = $this->getCart();

        $this->event->push('cart.updating', [$row, $cart]);

        if (is_array($attribute))
        {
            $raw = $this->updateAttribute($rawId, $attribute);
        } else
        {
            $raw = $this->updateQty($rawId, $attribute);
        }

        $this->event->push('cart.updated', [$row, $cart]);

        return $raw;
    }

    /**
     *  Add a row in Shopping Cart if exist then update it
     *
     * @param $id
     * @param $name
     * @param $qty
     * @param $price
     * @param array $attributes
     * @return bool|Item|mixed
     * @throws Exception
     */
    protected function addRow($id, $name, $qty, $price, array $attributes = [])
    {
        if (!is_numeric($qty) || $qty < 1)
        {
            throw new Exception('Invalid quantity.');
        }

        if (!is_numeric($price) || $price < 0)
        {
            throw new Exception('Invalid price.');
        }
        $cart = $this->getCart();

        $rawId = $this->generateRawId($id, $attributes);

        if ($row = $cart->get($rawId))
        {
            $row = $this->updateQty($rawId, $row->qty + $qty);
        } else
        {
            $row = $this->insertRow($rawId, $id, $name, $qty, $price, $attributes);
        }

        return $row;
    }

    /**
     * Insert a new row in Shopping Cart
     *
     * @param $rawId
     * @param $id
     * @param $name
     * @param $qty
     * @param $price
     * @param array $attributes
     * @return Item
     */
    protected function insertRow($rawId, $id, $name, $qty, $price, $attributes = [])
    {
        $newRow = $this->makeRow($rawId, $id, $name, $qty, $price, $attributes);

        $cart = $this->getCart();

        $cart->put($rawId, $newRow);

        $this->save($cart);

        return $newRow;
    }

    /**
     *  Make a new row in Shopping Cart
     *
     * @param $rawId
     * @param $id
     * @param $name
     * @param $qty
     * @param $price
     * @param array $attributes
     * @return Item
     */
    protected function makeRow($rawId, $id, $name, $qty, $price, array $attributes = [])
    {
        return new Item(array_merge(
            [
                '__raw_id' => $rawId,
                'id' => $id,
                'name' => $name,
                'qty' => $qty,
                'price' => $price,
                'total' => $qty * $price,
                '__model' => $this->model,
            ],
            $attributes));
    }

    /**
     *  Update quantity of a Row
     *
     * @param $rawId
     * @param $qty
     * @return bool|mixed
     */
    protected function updateQty($rawId, $qty)
    {
        if ($qty <= 0) {
            return $this->remove($rawId);
        }
        return $this->updateRow($rawId, ['qty' => $qty]);
    }

    /**
     *  Update whole row of Shopping Cart
     *
     * @param $rawId
     * @param array $attributes
     * @return mixed
     */
    protected function updateRow($rawId, array $attributes)
    {
        $cart = $this->getCart();
        $row = $cart->get($rawId);
        foreach ($attributes as $key => $value)
        {
            $row->put($key, $value);
        }

        if (count(array_intersect(array_keys($attributes), ['qty', 'price'])))
        {
            $row->put('total', $row->qty * $row->price);
        }

        $cart->put($rawId, $row);

        return $row;
    }

    /**
     *   Update selected attributes of Shopping Cart
     *
     * @param $rawId
     * @param $attributes
     * @return mixed
     */
    protected function updateAttribute($rawId, $attributes)
    {
        return $this->updateRow($rawId, $attributes);
    }

    /**
     *  Save record to Session
     *
     * @param $cart
     * @return mixed
     */
    protected function save($cart)
    {
        $this->session->put($this->name, $cart);
        return $cart;
    }

    /**
     *  Remove an Item from Shopping Cart
     *
     * @param $rawId
     * @return bool
     */
    public function remove($rawId)
    {
        if (!$row = $this->get($rawId))
        {
            return true;
        }

        $cart = $this->getCart();

        $this->event->push('cart.removing', [$row, $cart]);
        $cart->forget($rawId);

        $this->event->push('cart.removed', [$row, $cart]);

        $this->save($cart);

        return true;
    }

    /**
     *  Destroy the current Shopping Cart
     *
     * @return bool
     */
    public function destroy()
    {
        $cart = $this->getCart();

        $this->event->push('cart.destroying', $cart);

        $this->save(null);

        $this->event->push('cart.destroyed', $cart);

        return true;
    }

    /**
     * An alias to ShoppingCart::destroy();
     */
    public function clean()
    {
        $this->destroy();
    }

    /**
     *  Return Total of Shopping Cart
     *
     * @return int
     */
    public function total()
    {
        return $this->totalPrice();
    }

    /**
     *  Calculate totals for all Items in Shopping Cart
     *
     * @return int
     */
    public function totalPrice()
    {
        $total = 0;

        $cart = $this->getCart();

        if ($cart->isEmpty())
        {
            return $total;
        }

        foreach ($cart as $row)
        {
            $total += $row->qty * $row->price;
        }

        return $total;
    }

    /**
     *  Check if Shopping Cart is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->count() <= 0;
    }

    /**
     *  Returns the quantity of all items
     *
     * @param bool $totalItems
     * @return int
     */
    public function count($totalItems = true)
    {
        $items = $this->getCart();
        if (!$totalItems)
        {
            return $items->count();
        }

        $count = 0;

        foreach ($items as $row)
        {
            $count += $row->qty;
        }

        return $count;
    }

    /**
     *   Return the number of rows
     *
     * @return int
     */
    public function countRows()
    {
        return $this->count(false);
    }

    /**
     *  Search items by property
     *
     * @param array $search
     * @return Collection
     */
    public function search(array $search)
    {
        $rows = new Collection();
        if (empty($search))
        {
            return $rows;
        }

        foreach ($this->getCart() as $item)
        {
            if (array_intersect_assoc($item->intersect($search)->toArray(), $search))
            {
                $rows->put($item->__raw_id, $item);
            }
        }

        return $rows;
    }
}