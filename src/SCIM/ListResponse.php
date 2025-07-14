<?php

namespace ArieTimmerman\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class ListResponse implements Jsonable
{
    public function __construct(
        protected Collection $resourceObjects,
        protected $startIndex = 1,
        protected $totalResults = 10,
        protected array $attributes = [],
        protected $excludedAttributes = [],
        protected ?ResourceType $resourceType = null,
        protected $nextCursor = null,
        protected $previousCursor = null
    ) {
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toSCIMArray(), $options);
    }

    public function toSCIMArray()
    {
        return array_filter(
            [
               'totalResults' => $this->totalResults,
               "itemsPerPage" => count($this->resourceObjects->toArray()),

               "startIndex" => $this->startIndex,

               "nextCursor" => $this->nextCursor,
               "previousCursor" => $this->previousCursor,

               "schemas" => [
                   "urn:ietf:params:scim:api:messages:2.0:ListResponse"
               ],
               'Resources' => Helper::prepareReturn($this->resourceObjects, $this->resourceType, $this->attributes),
        ],
            fn ($value) => $value !== null
        );
    }
}
