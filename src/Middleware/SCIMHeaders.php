<?php

namespace ArieTimmerman\Laravel\SCIMServer\Middleware;

use Closure;
use Illuminate\Http\Request;

class SCIMHeaders{

    public function handle(Request $request, Closure $next) {

//         var_dump($request->header('content-type'));
//         var_dump($request->getContent());
        
        
        //var_dump($request->input("schemas"));exit;
        //if($request->header('content-type'))
        
        $response = $next($request);

        return $response->header('Content-Type', 'application/scim+json');
    }


}