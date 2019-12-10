<?php

namespace ArieTimmerman\Laravel\SCIMServer\Events;

use Illuminate\Database\Eloquent\Model;

interface EventInterface
{
    public function __construct(Model $model, bool $me = null);
}
