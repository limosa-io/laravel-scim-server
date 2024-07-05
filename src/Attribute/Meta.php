<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

class Meta extends Complex
{
    public function __construct($resourceType){
        parent::__construct('meta', false);
        $this->setMutability('readOnly')->withSubAttributes(
            new Eloquent('created', 'created_at'),
            new Eloquent('lastModified', 'updated_at'),
            (new class ('location', $resourceType) extends Eloquent {

                function __construct($name, public $resourceType)
                {
                    parent::__construct($name);
                }

                protected function doRead(&$object, $attributes = [])
                {
                    return route(
                        'scim.resource',
                        [
                        'resourceType' => $this->resourceType,
                        'resourceObject' => $object->id ?? "not-saved"
                        ]
                    );
                }
            }),
            // get substring of $resourceType, everything till the last character
            new Constant('resourceType', substr($resourceType, 0, -1))
        );
    }
}