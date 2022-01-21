<?php
/**
 * Laravel service provider for registering the routes and publishing the configuration.
 */

namespace ArieTimmerman\Laravel\SCIMServer;

use Illuminate\Support\Facades\Route;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(\Illuminate\Routing\Router $router)
    {
        $this->loadMigrationsFrom(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
        
        $this->publishes([
            __DIR__.'/../config/scim.php' => config_path('scim.php'),
        ], 'laravel-scim');

        // Match everything, except the Me routes
        $router->pattern('resourceType', '^((?!Me).)*$');

        $router->bind(
            'resourceType',
            function ($name, $route) {
                $config = resolve(SCIMConfig::class)->getConfigForResource($name);
            
                if ($config == null) {
                    throw (new SCIMException(sprintf('No resource "%s" found.', $name)))->setCode(404);
                }
            
                return new ResourceType($name, $config);
            }
        );
        
        $router->bind(
            'resourceObject',
            function ($id, $route) {
                $resourceType = $route->parameter('resourceType');
            
                if (!$resourceType) {
                    throw (new SCIMException('ResourceType not provided'))->setCode(404);
                }
            
                $class = $resourceType->getClass();
            
                $resourceObject = $class::with($resourceType->getWithRelations())->find($id);
                         
                if ($resourceObject == null) {
                    throw (new SCIMException(sprintf('Resource "%s" not found', $id)))->setCode(404);
                }
            
                if (($matchIf = \request()->header('IF-Match'))) {
                    $versionsAllowed = preg_split('/\s*,\s*/', $matchIf);
                    $currentVersion = Helper::getResourceObjectVersion($resourceObject);
                
                    //if as version is '*' it is always ok
                    if (!in_array($currentVersion, $versionsAllowed) && !in_array('*', $versionsAllowed)) {
                        throw (new SCIMException('Failed to update.  Resource changed on the server.'))->setCode(412);
                    }
                }
            
                return $resourceObject;
            }
        );
        
        $router->middleware('SCIMHeaders', 'ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders');

        if (config('scim.publish_routes')) {
            \ArieTimmerman\Laravel\SCIMServer\RouteProvider::routes();
        }
    }
    
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/scim.php',
            'scim'
        );
    }
}
