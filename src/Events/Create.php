<?php

namespace ArieTimmerman\Laravel\SCIMServer\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class Create extends AbstractEvent
{
    use SerializesModels;

    public $model;
    public $me;

    /**
     * Create a new event instance.
     *
     * @param  \App\Order $order
     * @return void
     */
    public function __construct(Model $model, bool $me = null)
    {
        $this->model = $model;
        $this->me = $me;
    }
}
