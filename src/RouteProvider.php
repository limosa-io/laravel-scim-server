<?php
namespace ArieTimmerman\Laravel\SCIMServer;

use Route;
use Illuminate\Support\Facades\Auth;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

/**
 * Helper class for the URL shortener
 */
class RouteProvider
{

    public static function routes(array $options = [])
    {
        $prefix = 'scim';
        
        Route::prefix($prefix)->group(function () use($options, $prefix) {
            
            Route::prefix('v2')->middleware([
                'bindings',
                'ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders'
            ])
                ->group(function () use($options) {
                self::allRoutes($options);
            });
            
            $routeWrongVersion = function () use($prefix) {
                throw (new SCIMException('Only SCIM v2 is supported. Accessible under ' . url($prefix . '/v2')))->setCode(501)
                    ->setScimType('invalidVers');
            };
            
            Route::get('v1', $routeWrongVersion);
            Route::prefix('v1')->group(function () use($options, $routeWrongVersion) {
                Route::fallback($routeWrongVersion);
            });
            
        });
    }

    private static function allRoutes(array $options = [])
    {
        Route::bind('resourceType', function ($name) {
            
            $config = @config("scimserver")[$name];
            
            if ($config == null) {
                throw (new SCIMException(sprintf('No resource "%s" found.', $name)))->setCode(404);
            }
            
            return new ResourceType($name, $config);
        });
        
        Route::get('/Me', function () {
            return Helper::objectToSCIMArray(Auth::user(), ResourceType::user());
        });
        
        Route::get("/ServiceProviderConfig", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ServiceProviderController@index')->name('scim.serviceproviderconfig');
        
        Route::get("/Schemas", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController@index');
        Route::get("/Schemas/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController@show')->name('scim.schemas');
        
        Route::get("/ResourceTypes", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController@index');
        Route::get("/ResourceTypes/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController@show')->name('scim.resourcetype');
        
        // TODO: Use the attributes parameters ?attributes=userName, excludedAttributes=asdg,asdg (respect "returned" settings "always")        
        Route::get('/{resourceType}/{id}', 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@show')->name('scim.resource');
        Route::get("/{resourceType}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@index')->name('scim.resources');
        
        Route::post("/{resourceType}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@create');
        
        Route::put("/{resourceType}/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@replace');
        Route::patch("/{resourceType}/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@update');
        Route::delete("/{resourceType}/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@delete');
        
        Route::fallback(function () {
            return response(null, 501);
        });
    }
}
