<?php

namespace ArieTimmerman\Laravel\SCIMServer\Middleware;

use Closure;
use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class SCIMHeaders
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->method() != 'GET' && stripos($request->header('content-type'), 'application/scim+json') === false && stripos($request->header('content-type'), 'application/json') === false && strlen($request->getContent()) > 0) {
            throw new SCIMException(sprintf('The content-type header should be set to "%s"', 'application/scim+json'));
        }
        
        $response = $next($request);
        
        return $response->header('Content-Type', 'application/scim+json');
    }
}
