<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;

class PolicyDecisionPoint
{
    const OPERATION_GET = 'GET';
    const OPERATION_POST = 'POST';
    const OPERATION_DELETE = 'DELETE';
    const OPERATION_PATCH = 'PATCH';
    const OPERATION_PUT = 'PUT';
    
    public function isAllowed(
        Request $request,
        $operation,
        array $attributes,
        ResourceType $resourceType,
        ?Model $resourceObject,
        $isMe = false
    ) {
        if ($isMe) {
        }
        
        return true;
    }

    public function getAllowedAttributes($request, $operation, $object, $attributes, $context)
    {

        // TODO: not in use yet
    }
}
