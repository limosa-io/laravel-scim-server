<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

class Schema extends Complex
{

    public function generateSchema()
    {
        return [
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:Schema"
            ],
            "id" => $this->name,
            "meta" => [
                "resourceType" => "Schema",
                "created" => "2001-01-01T00:00:00+00:00",
                "lastModified" => "2001-01-01T00:00:00+00:00",
                "version" => 'W/"1"',
                "location" => route('scim.schemas', ['id' => $this->name])
            ],
            // name is substring after last occurence of :
            "name" => substr($this->name, strrpos($this->name, ':') + 1),
            "description" => $this->description,
            "attributes" => collect($this->subAttributes)->map(fn ($element) => $element->generateSchema())->toArray()
        ];
    }

}
