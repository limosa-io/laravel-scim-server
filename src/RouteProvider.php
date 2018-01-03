<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use Route;
use Illuminate\Support\Facades\Auth;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

/**
 * Helper class for the URL shortener
 */
class RouteProvider {
	
	public static function routes(array $options = []) {
			
		$prefix = "scim/v2";
				
		Route::prefix($prefix)->middleware(['bindings','ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders'])->group(function() use ($options){
			self::allRoutes($options);
		});
		
	}
	
	private static function allRoutes(array $options = []){
	    
	    
        Route::bind('resourceType', function ($name) {
            
            $config = @config("scimserver")[$name];
            
            if($config == null){
                throw new SCIMException("Not found",404);
            }
            
            return new ResourceType($name, $config);
        });
	    
		Route::get('/Me', function (){
		    return Helper::objectToSCIMArray(Auth::user());
		});
		
		Route::get("/ServiceProviderConfig", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ServiceProviderController@index')->name('scim.serviceproviderconfig');
		
		Route::get("/Schemas", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController@index');
		Route::get("/Schemas/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController@show')->name('scim.schemas');
		
		Route::get("/ResourceTypes", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController@index');
		Route::get("/ResourceTypes/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController@show')->name('scim.resourcetype');
				
		// TODO: Use the attributes parameters ?attributes=userName, excludedAttributes=asdg,asdg (respect "returned" settings "always")
		// TODO: Support ETag
		
		Route::get('/{resourceType}/{id}', 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@show')->name('scim.resource');;
		Route::get("/{resourceType}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@index');
		
		Route::post("/{resourceType}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@create');
		
		//replace
		Route::put("/{resourceType}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@replace');
		
		//modify
		Route::patch("/{resourceType}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@update');
		
		// TODO: implement DELETE
		Route::delete("/{resourceType}", function(){
		    return response(null,501);
		});
		
		Route::fallback(function(){
		    return response(null,501); 
		});
		
	}
	
	
	
	
	
}
