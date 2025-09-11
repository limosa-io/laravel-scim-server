<?php

namespace ArieTimmerman\Laravel\SCIMServer\Events;

use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractEvent implements EventInterface
{
    use SerializesModels;

    public $model;

    /**
     * @var boolean
     */
    public $me;

    public $resourceType;

    public function getModel()
    {
        return $this->model;
    }

    public function isMe()
    {
        return $this->me;
    }

    public function getResourceType(): ResourceType
    {
        return $this->resourceType;
    }

    public function __construct(Model $model, ResourceType $resourceType, ?bool $me = null, public $input = null, public $oldObjectArray = [])
    {
        $this->model = $model;
        $this->resourceType = $resourceType;
        $this->me = $me;
    }
}
