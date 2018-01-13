<?php

namespace ArieTimmerman\Laravel\SCIMServer\Middleware;

use Closure;
use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class SCIMHeaders{

    public function handle(Request $request, Closure $next) {
        
        if($request->method() != 'GET' && !empty($request->input()) && $request->header('content-type') != 'application/scim+json' && $request->header('content-type') != 'application/json'){
            throw new SCIMException(sprintf('The content-type header should be set to "%s"','application/scim+json'));
        }
        
        $response = $next($request);
        
        //TODO: Consider adding debug mode, to validate response agains schema.

        return $response->header('Content-Type', 'application/scim+json');
    }


}