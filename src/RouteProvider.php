<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/**
 * Registers SCIM routes with configurable prefix, domain and middleware.
 */
class RouteProvider
{

    public static function routes(array $options = [])
    {
        $config = static::getRoutingConfig($options);
        
        if (!isset($options['public_routes']) || $options['public_routes'] === true) {
            static::publicRoutes([
                'prefix' => $config['prefix'],
                'middleware' => $config['public_middleware'],
                'domain' => $config['domain'],
            ]);
        }

        $group = static::buildRouteGroup($config, 'middleware');

        Route::group($group, function () use ($options) {
            Route::prefix('v2')->middleware([
                SubstituteBindings::class,
                SCIMHeaders::class,
            ])->group(function () use ($options) {
                static::allRoutes($options);
            });

            Route::get('v1', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'wrongVersion']);
            Route::prefix('v1')->group(function () {
                Route::fallback([\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'wrongVersion']);
            });
        });
    }

    public static function meRoutes(array $options = [])
    {
        $config = static::getRoutingConfig($options);
        $group = static::buildRouteGroup($config, 'middleware');

        Route::group($group, function () {
            Route::get('/v2/Me', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController::class, 'getMe'])->name('scim.me.get');
            Route::put('/v2/Me', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController::class, 'replaceMe'])->name('scim.me.put');
        });
    }

    public static function meRoutePost(array $options = [])
    {
        $config = static::getRoutingConfig($options);
        $group = static::buildRouteGroup($config, 'middleware');

        Route::group($group, function () {
            Route::post('/v2/Me', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController::class, 'createMe'])->name('scim.me.post');
        });
    }

    public static function publicRoutes(array $options = [])
    {
        if (isset($options['prefix']) && isset($options['middleware'])) {
            $config = $options;
        } else {
            $config = static::getRoutingConfig($options);
            $config['middleware'] = $config['public_middleware'];
        }

        $group = static::buildRouteGroup($config, 'middleware');

        Route::group($group, function () {
            Route::get('/v2/ServiceProviderConfig', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ServiceProviderController::class, 'index'])->name('scim.serviceproviderconfig');

            Route::get('/v2/Schemas', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController::class, 'index']);
            Route::get('/v2/Schemas/{id}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController::class, 'show'])->name('scim.schemas');

            Route::get('/v2/ResourceTypes', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController::class, 'index']);
            Route::get('/v2/ResourceTypes/{id}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController::class, 'show'])->name('scim.resourcetype');
        });
    }

    private static function allRoutes(array $options = [])
    {
        Route::get('/', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'crossResourceIndex']);
        Route::get('', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'crossResourceIndex']);
        Route::post('.search', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'crossResourceSearch']);

        Route::post('/Bulk', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\BulkController::class, 'processBulkRequest']);

        // TODO: Use the attributes parameters ?attributes=userName, excludedAttributes=asdg,asdg (respect "returned" settings "always")
        Route::get('/{resourceType}/{resourceObject}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'show'])->name('scim.resource');
        Route::get('/{resourceType}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'index'])->name('scim.resources');
        Route::post('/{resourceType}/.search', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'search']);
        Route::post('/{resourceType}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'create']);

        Route::put('/{resourceType}/{resourceObject}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'replace']);
        Route::patch('/{resourceType}/{resourceObject}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'update']);
        Route::delete('/{resourceType}/{resourceObject}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'delete']);

        Route::fallback([\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'notImplemented']);
    }

    private static function getRoutingConfig(array $options = []): array
    {
        $path = $options['path'] ?? config('scim.path', '/scim');
        $domain = $options['domain'] ?? config('scim.domain');
        $protectedMiddleware = static::normalizeMiddleware($options['middleware'] ?? config('scim.middleware', []));
        $publicMiddleware = static::normalizeMiddleware($options['public_middleware'] ?? config('scim.public_middleware', []));
        
        $prefix = trim($path, '/');
        if (empty($prefix)) {
            $prefix = 'scim';
        }
        
        return [
            'prefix' => $prefix,
            'domain' => $domain,
            'middleware' => $protectedMiddleware,
            'public_middleware' => $publicMiddleware,
        ];
    }
    
    private static function buildRouteGroup(array $config, string $middlewareKey = 'middleware'): array
    {
        $group = ['prefix' => $config['prefix']];
        
        $middleware = $config[$middlewareKey] ?? [];
        if (!empty($middleware)) {
            $group['middleware'] = $middleware;
        }
        
        if (!empty($config['domain'])) {
            $group['domain'] = $config['domain'];
        }
        
        return $group;
    }

    private static function normalizeMiddleware($middleware): array
    {
        if (empty($middleware)) {
            return [];
        }

        if (is_string($middleware)) {
            $middleware = array_filter(array_map('trim', explode(',', $middleware)));
        }

        return is_array($middleware) ? $middleware : [$middleware];
    }
}
