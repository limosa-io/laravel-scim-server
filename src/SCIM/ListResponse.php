<?php

namespace ArieTimmerman\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;

class ListResponse implements Jsonable{


    private $resourceObjects = [], $startIndex, $totalResults, $attributes, $excludedAttributes;
    private $resourceType = null;
    

    function __construct($resourceObjects, $startIndex = 1, $totalResults = 10, $attributes = [], $excludedAttributes = [], ResourceType $resourceType = null){
        $this->resourceType = $resourceType;
        $this->resourceObjects = $resourceObjects;
        $this->startIndex = $startIndex;
        $this->totalResults = $totalResults;
        $this->attribtues = $attributes;
        $this->excludedAttributes = $excludedAttributes;

    }

    public function toJson($options = 0) {
        return json_encode($this->toSCIMArray(), $options);
    }

    // Ensure  "Content-Type: application/scim+json"
    public function toSCIMArray() {
        
        // If items implement SCIMUser (check with class_uses($this->items[0]) or similar

        return [
            'totalResults' => $this->totalResults, // (total without pagination)
            "itemsPerPage" => count($this->resourceObjects),
            "startIndex" => $this->startIndex,            
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:ListResponse"
            ],
            'Resources' => Helper::prepareReturn($this->resourceObjects, $this->resourceType),
        ];
        
    }
    
}