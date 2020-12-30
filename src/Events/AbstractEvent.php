<?php

namespace ArieTimmerman\Laravel\SCIMServer\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractEvent implements EventInterface
{
    public $model;

    /**
     * @var boolean
     */
    public $me;

    public function getModel()
    {
        return $this->model;
    }

    public function isMe()
    {
        return $this->me;
    }
}
