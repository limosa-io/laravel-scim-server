<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use Route;
use Illuminate\Support\Facades\Auth;

/**
 * Helper class for the URL shortener
 */
class RouteProvider {
	
	public static function routes(array $options = []) {
			
		$prefix = "scim/v2";
				
		Route::prefix($prefix)->middleware(['ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders'])->group(function() use ($options){
			self::allRoutes($options);
		});
		
	}
	
	private static function allRoutes(array $options = []){
	    
		Route::get('/Me', function (){
		    return Auth::user();
		});
		
		Route::get("/ServiceProviderConfig", 'ArieTimmerman\Laravel\SCIMServer\Controllers\ServiceProviderController@index')->name('scim.serviceproviderconfig');
		
		Route::get("/Schemas", 'ArieTimmerman\Laravel\SCIMServer\Controllers\SchemaController@index');
		Route::get("/Schemas/{id}", 'ArieTimmerman\Laravel\SCIMServer\Controllers\SchemaController@show')->name('scim.schemas');
		
		Route::get("/ResourceTypes", 'ArieTimmerman\Laravel\SCIMServer\Controllers\ResourceTypesController@index');
		Route::get("/ResourceTypes/{id}", 'ArieTimmerman\Laravel\SCIMServer\Controllers\ResourceTypesController@show')->name('scim.resourcetype');
				
		// TODO: Use the attributes parameters ?attributes=userName, excludedAttributes=asdg,asdg (respect "returned" settings "always")
		// TODO: Support ETag
		
		Route::get('/{name}/{id}', 'ArieTimmerman\Laravel\SCIMServer\Controllers\ResourceController@show')->name('scim.resource');;
		Route::get("/{name}", 'ArieTimmerman\Laravel\SCIMServer\Controllers\ResourceController@index');
		
		Route::post("/{name}", 'ArieTimmerman\Laravel\SCIMServer\Controllers\ResourceController@create');
		
		//replace
		Route::put("/{name}", function(){
		    return response(null,501);
		});
		
		//modify
		Route::patch("/{name}", 'ArieTimmerman\Laravel\SCIMServer\Controllers\ResourceController@update');
		
		// TODO: implement DELETE
		Route::delete("/{name}", function(){
		    return response(null,501);
		});
		
		Route::fallback(function(){
		    return response(null,501); 
		});
		
	}
	
	
	
	
	
}
