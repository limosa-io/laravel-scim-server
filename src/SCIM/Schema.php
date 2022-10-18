<?php

namespace ArieTimmerman\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;

class Schema implements Jsonable
{
    const SCHEMA_USER = "urn:ietf:params:scim:schemas:core:2.0:User";
    const SCHEMA_GROUP = "urn:ietf:params:scim:schemas:core:2.0:Group";
    
    const ATTRIBUTES_CORE = ["id","externalId","meta","schemas"];
    
    public function toJson($options = 0)
    {
        return [];
    }
}
