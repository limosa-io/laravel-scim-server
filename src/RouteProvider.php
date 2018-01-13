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
        Route::bind('resourceType', function ($name, $route) {
            
            $config = @config("scimserver")[$name];
            
            if ($config == null) {
                throw (new SCIMException(sprintf('No resource "%s" found.', $name)))->setCode(404);
            }
            
            return new ResourceType($name, $config);
            
        });
        
        Route::bind('resourceObject', function ($id, $route) {
            
            $resourceType = $route->parameter('resourceType');
            
            if(!$resourceType){
                throw (new SCIMException('ResourceType not provided'))->setCode(404);
            }
            
            $class = $resourceType->getClass();
             
            $resourceObject = $class::find($id);
                         
            if($resourceObject == null){
                throw (new SCIMException(sprintf('Resource "%s" not found',$id)))->setCode(404);
            }
            
            if( ($matchIf = \request()->header('IF-Match')) ){
            
                $versionsAllowed = preg_split('/\s*,\s*/', $matchIf);
                $currentVersion = Helper::getResourceObjectVersion($resourceObject);
                
                //if as version is '*' it is always ok
                if( !in_array($currentVersion, $versionsAllowed) && !in_array('*', $versionsAllowed)){
                    throw (new SCIMException('Failed to update.  Resource changed on the server.'))->setCode(412);
                }
            
            }
            
            return $resourceObject;

        });
        
        Route::get('/Me', function () {
            return Helper::objectToSCIMArray(Auth::user(), ResourceType::user());
        });
        
        Route::get("/ServiceProviderConfig", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ServiceProviderController@index')->name('scim.serviceproviderconfig');
        
        Route::get("/Schemas", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController@index');
        Route::get("/Schemas/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController@show')->name('scim.schemas');
        
        Route::get("/ResourceTypes", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController@index');
        Route::get("/ResourceTypes/{id}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController@show')->name('scim.resourcetype');
        
        Route::post('.search',function () {
            return response(null, 501);
        });
        
        // TODO: Use the attributes parameters ?attributes=userName, excludedAttributes=asdg,asdg (respect "returned" settings "always")        
        Route::get('/{resourceType}/{resourceObject}', 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@show')->name('scim.resource');
        Route::get("/{resourceType}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@index')->name('scim.resources');
        
        Route::post("/{resourceType}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@create');
        
        Route::put("/{resourceType}/{resourceObject}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@replace');
        Route::patch("/{resourceType}/{resourceObject}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@update');
        Route::delete("/{resourceType}/{resourceObject}", 'ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController@delete');
        
        Route::fallback(function () {
            return response(null, 501);
        });
        
    }
}
