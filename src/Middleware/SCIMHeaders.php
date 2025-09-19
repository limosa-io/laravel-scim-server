<?php

namespace ArieTimmerman\Laravel\SCIMServer\Middleware;

use Closure;
use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class SCIMHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $contentType = $request->header('content-type', '');

        if ($request->method() != 'GET' && stripos($contentType, 'application/scim+json') === false && stripos($contentType, 'application/json') === false && strlen($request->getContent()) > 0) {
            throw new SCIMException(sprintf('The content-type header should be set to "%s"', 'application/scim+json'));
        }
        
        $response = $next($request);
        
        return $response->header('Content-Type', 'application/scim+json');
    }
}
