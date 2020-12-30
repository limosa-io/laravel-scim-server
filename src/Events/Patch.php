<?php

namespace ArieTimmerman\Laravel\SCIMServer\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class Patch extends AbstractEvent
{
    use SerializesModels;

    public $odlObjectArray;

    /**
     * Create a new event instance.
     *
     * @param  \App\Order $order
     * @return void
     */
    public function __construct(Model $model, bool $me = null, $odlObjectArray = [])
    {
        $this->model = $model;
        $this->me = $me;
        $this->odlObjectArray = $odlObjectArray;
    }
}
