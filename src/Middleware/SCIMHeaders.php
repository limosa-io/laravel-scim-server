<?php

namespace ArieTimmerman\Laravel\SCIMServer\Middleware;

use Closure;
use Illuminate\Http\Request;

class SCIMHeaders{

    public function handle(Request $request, Closure $next) {

        //if($request->header('content-type'))
        
        $response = $next($request);

        return $response->header('Content-Type', 'application/scim+json');
    }


}