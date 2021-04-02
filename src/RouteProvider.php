<?php
namespace ArieTimmerman\Laravel\SCIMServer;

use Illuminate\Support\Facades\Auth;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Helper class for the URL shortener
 */
class RouteProvider
{
    protected static $prefix = 'scim';

    public static function routes(array $options = [])
    {

        if (!isset($options['public_routes']) || $options['public_routes'] === true) {
            self::publicRoutes($options);
        }

        Route::prefix(self::$prefix)->group(
            function () use ($options) {
                Route::prefix('v2')->middleware(
                    [
                    // TODO: Not loading this middleware introduces resolve issues. But having it, might slow things down.
                    \Illuminate\Routing\Middleware\SubstituteBindings::class,
                    'ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders'
                    ]
                )->group(
                    function () use ($options) {
                        self::allRoutes($options);
                    }
                );
            
                Route::get('v1', '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@wrongVersion');
                Route::prefix('v1')->group(
                    function () {
                        Route::fallback('\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@wrongVersion');
                    }
                );
            }
        );
    }

    public static function meRoutes(array $options = [])
    {
        Route::get('/scim/v2/Me', '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController@getMe')->name('scim.me.get');
        Route::put('/scim/v2/Me', '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController@replaceMe')->name('scim.me.put');
    }

    public static function meRoutePost(array $options = [])
    {
        Route::post('/scim/v2/Me', '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController@createMe')->name('scim.me.post');
    }

    public static function publicRoutes(array $options = [])
    {
        Route::get("/scim/v2/ServiceProviderConfig", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ServiceProviderController@index')->name('scim.serviceproviderconfig');
        
        Route::get("/scim/v2/Schemas", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController@index');
        Route::get("/scim/v2/Schemas/{id}", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController@show')->name('scim.schemas');
        
        Route::get("/scim/v2/ResourceTypes", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController@index');
        Route::get("/scim/v2/ResourceTypes/{id}", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController@show')->name('scim.resourcetype');
    }

    private static function allRoutes(array $options = [])
    {
        Route::post('.search', '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@notImplemented');
        
        // TODO: Use the attributes parameters ?attributes=userName, excludedAttributes=asdg,asdg (respect "returned" settings "always")
        Route::get('/{resourceType}/{resourceObject}', '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@show')->name('scim.resource');
        Route::get("/{resourceType}", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@index')->name('scim.resources');
        
        Route::post("/{resourceType}", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@create');
        
        Route::put("/{resourceType}/{resourceObject}", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@replace');
        Route::patch("/{resourceType}/{resourceObject}", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@update');
        Route::delete("/{resourceType}/{resourceObject}", '\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@delete');
        
        Route::fallback('\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@notImplemented');
    }
}
