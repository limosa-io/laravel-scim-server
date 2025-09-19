<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Middleware\SubstituteBindings;

/**
 * Helper class for the URL shortener
 */
class RouteProvider
{
    protected static $prefix = 'scim';

    public static function routes(array $options = [])
    {

        if (!isset($options['public_routes']) || $options['public_routes'] === true) {
            static::publicRoutes($options);
        }

        Route::prefix(static::$prefix)->group(
            function () use ($options) {
                Route::prefix('v2')->middleware([
                    SubstituteBindings::class,
                    SCIMHeaders::class,
                ])->group(
                    function () use ($options) {
                        static::allRoutes($options);
                    }
                );

                Route::get('v1', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'wrongVersion']);
                Route::prefix('v1')->group(
                    function () {
                        Route::fallback([\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'wrongVersion']);
                    }
                );
            }
        );
    }

    public static function meRoutes(array $options = [])
    {
        Route::prefix(static::$prefix)->group(function () {
            Route::get("/v2/Me", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController::class, 'getMe'])->name('scim.me.get');
            Route::put('/v2/Me', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController::class, 'replaceMe'])->name('scim.me.put');
        });
    }

    public static function meRoutePost(array $options = [])
    {
        Route::prefix(static::$prefix)->group(function () {
            Route::post('/v2/Me', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController::class, 'createMe'])->name('scim.me.post');
        });
    }

    public static function publicRoutes(array $options = [])
    {
        Route::prefix(static::$prefix)->group(function () {
            Route::get("/v2/ServiceProviderConfig", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ServiceProviderController::class, 'index'])->name('scim.serviceproviderconfig');

            Route::get("/v2/Schemas", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController::class, 'index']);
            Route::get("/v2/Schemas/{id}", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\SchemaController::class, 'show'])->name('scim.schemas');

            Route::get("/v2/ResourceTypes", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController::class, 'index']);
            Route::get("/v2/ResourceTypes/{id}", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceTypesController::class, 'show'])->name('scim.resourcetype');
        });
    }

    private static function allRoutes(array $options = [])
    {
        Route::get('/', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'crossResourceIndex']);
        Route::get('', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'crossResourceIndex']);
        Route::post('.search', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'crossResourceSearch']);

        Route::post("/Bulk", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\BulkController::class, 'processBulkRequest']);

        // TODO: Use the attributes parameters ?attributes=userName, excludedAttributes=asdg,asdg (respect "returned" settings "always")
        Route::get('/{resourceType}/{resourceObject}', [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'show'])->name('scim.resource');
        Route::get("/{resourceType}", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'index'])->name('scim.resources');
        Route::post("/{resourceType}/.search", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'search']);
        Route::post("/{resourceType}", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'create']);

        Route::put("/{resourceType}/{resourceObject}", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'replace']);
        Route::patch("/{resourceType}/{resourceObject}", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'update']);
        Route::delete("/{resourceType}/{resourceObject}", [\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'delete']);

        Route::fallback([\ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController::class, 'notImplemented']);
    }
}
