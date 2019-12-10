<?php

namespace ArieTimmerman\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;

class ResourceType implements Jsonable
{
    public $id;
    
    public $name;
    
    public $plurar;
    
    public $description;
    
    public $schema;
    
    public $schemaExtensions;
    
    public function __construct($id, $name, $plurar, $description, $schema, $schemaExtensions = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->plurar = $plurar;
        $this->description = $description;
        $this->schema = $schema;
        $this->schemaExtensions = $schemaExtensions;
    }
    
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }
    
    public function toArray()
    {
        return [
                "schemas" => [
                        "urn:ietf:params:scim:schemas:core:2.0:ResourceType"
                ],
                "id" => $this->id,
                "name" => $this->name,
                
                "endpoint" => route('scim.resources', ['resourceType'=>$this->plurar]),
                "description" => $this->description,
                "schema" => $this->schema,
                
                "schemaExtensions" => $this->schemaExtensions,
                
                "meta" => [
                        "location" => route('scim.resourcetype', ['id' => $this->id]),
                        "resourceType" => "ResourceType"
                ]
                
        ];
    }
}
