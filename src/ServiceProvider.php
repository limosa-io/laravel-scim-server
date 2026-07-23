<?php

/**
 * Laravel service provider for registering the routes and publishing the configuration.
 */

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Contracts\Container\BindingResolutionException;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(\Illuminate\Routing\Router $router)
    {
        $this->publishes([
            __DIR__ . '/../config/scim.php' => config_path('scim.php'),
        ], 'laravel-scim');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'laravel-scim-migrations');

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

                $query = $resourceType->getQuery();

                $resourceObject = $query->with($resourceType->getWithRelations())->find($id);

                if ($resourceObject == null) {
                    throw (new SCIMException(sprintf('Resource "%s" not found', $id)))->setCode(404);
                }

                if (($matchIf = \request()->header('IF-Match'))) {
                    $versionsAllowed = preg_split('/\s*,\s*/', $matchIf);
                    $currentVersion = Helper::getResourceObjectVersion($resourceObject);

                    //if as version is '*' it is always ok
                    if (!in_array($currentVersion, $versionsAllowed) && !in_array('*', $versionsAllowed)) {
                        throw (new SCIMException('Failed to update. Resource changed on the server.'))->setCode(412);
                    }
                }

                return $resourceObject;
            }
        );

        $router->middleware('SCIMHeaders', 'ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders');

        if (config('scim.publish_routes')) {
            $routeOptions = [
                'path' => config('scim.path', '/scim'),
                'domain' => config('scim.domain'),
                'middleware' => config('scim.middleware', []),
                'public_middleware' => config('scim.public_middleware', []),
            ];

            \ArieTimmerman\Laravel\SCIMServer\RouteProvider::routes($routeOptions);
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
            __DIR__ . '/../config/scim.php',
            'scim'
        );

        // Register a container binding for ResourceType so it can be resolved
        // from the current route parameter even when SubstituteBindings middleware
        // is not applied to the route (e.g. in custom user-defined routes).
        $this->app->bind(ResourceType::class, function ($app) {
            $route = $app->make('request')->route();

            if ($route) {
                $resourceType = $route->parameter('resourceType');

                if ($resourceType instanceof ResourceType) {
                    return $resourceType;
                }

                if (is_string($resourceType)) {
                    $config = $app->make(SCIMConfig::class)->getConfigForResource($resourceType);

                    if ($config !== null) {
                        return new ResourceType($resourceType, $config);
                    }

                    throw (new SCIMException(sprintf('No resource "%s" found.', $resourceType)))->setCode(404);
                }
            }

            throw new BindingResolutionException(
                'Cannot resolve ' . ResourceType::class . ': no "resourceType" route parameter found on the current request. ' .
                'Ensure your route defines a {resourceType} parameter (e.g. Route::get("{resourceType}", ...)) ' .
                'and that the route model binding or SubstituteBindings middleware is applied.'
            );
        });
    }
}
