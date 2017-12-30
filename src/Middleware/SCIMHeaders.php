<?php

namespace ArieTimmerman\Laravel\SCIMServer\Middleware;

use Closure;

class SCIMHeaders{

    public function handle($request, Closure $next) {

        $response = $next($request);

        return $response->header('Content-Type', 'application/scim+json');
    }


}