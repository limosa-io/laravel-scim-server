<?php

namespace ArieTimmerman\Laravel\SCIMServer\Events;

interface EventInterface{

    public function __construct(Model $model, boolean $me = null);




}