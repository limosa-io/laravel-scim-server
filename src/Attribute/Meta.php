<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Helper;
use Illuminate\Database\Eloquent\Model;

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
            new Constant('resourceType', substr($resourceType, 0, -1)),
            new class ('version', null) extends Constant {
                protected function doRead(&$object, $attributes = [])
                {
                    return Helper::getResourceObjectVersion($object);
                }
            }
        );
    }

    public function remove($value, Model &$object, ?string $path = null)
    {
        // ignore
    }

    public function add($value, Model &$object)
    {
        // ignore
    }
}