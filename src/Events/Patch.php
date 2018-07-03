<?php

namespace ArieTimmerman\Laravel\SCIMServer\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class Patch
{
    use SerializesModels;

    public $model;

    /**
     * Create a new event instance.
     *
     * @param  \App\Order  $order
     * @return void
     */
    public function __construct(Model $model, boolean $me = null)
    {
        $this->model = $model;
        $this->me = $me;
    }
}