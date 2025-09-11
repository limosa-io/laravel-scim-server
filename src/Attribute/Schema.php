<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

class Schema extends Complex
{
    function __construct($name, public $required = true)
    {
        parent::__construct($name);
    }

    #[\Override]
    public function generateSchema()
    {
        $result = [
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:Schema"
            ],
            "id" => $this->name,
            "meta" => [
                "resourceType" => "Schema",
                "created" => "2001-01-01T00:00:00+00:00",
                "lastModified" => "2001-01-01T00:00:00+00:00",
                "version" => sprintf('W/"%s"', sha1(filemtime(__FILE__))),
                "location" => route('scim.schemas', ['id' => $this->name])
            ],
            // name is substring after last occurence of :
            "name" => substr((string) $this->name, strrpos((string) $this->name, ':') + 1),
            "attributes" => collect($this->subAttributes)->map(fn ($element) => $element->generateSchema())->toArray()
        ];

        if($this->description !== null){
            $result['description'] = $this->description;
        }

        return $result;
    }

    public function getName(){
        return $this->name;
    }

}
