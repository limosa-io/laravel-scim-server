<?php

namespace ArieTimmerman\Laravel\SCIMServer\Events;

use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use Illuminate\Database\Eloquent\Model;

interface EventInterface
{
    public function __construct(Model $model, ResourceType $resourceType, bool $me = null, $input, $odlObjectArray = []);
}
