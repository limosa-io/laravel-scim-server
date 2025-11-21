![](https://github.com/arietimmerman/laravel-scim-server/workflows/CI/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/arietimmerman/laravel-scim-server/v/stable)](https://packagist.org/packages/arietimmerman/laravel-scim-server)
[![Total Downloads](https://poser.pugx.org/arietimmerman/laravel-scim-server/downloads)](https://packagist.org/packages/arietimmerman/laravel-scim-server)

![Logo of Laravel SCIM Server, the SCIM server implementation from scim.dev, SCIM Playground](./laravel-scim-server.svg)

# SCIM 2.0 Server implementation for Laravel

Add SCIM 2.0 Server capabilities to your Laravel application with ease. This package requires minimal configuration to get started with the core SCIM flows and is powering [The SCIM Playground](https://scim.dev), one of the most widely tested SCIM servers available.

## Why Laravel SCIM Server?
- Battle-tested with real-world providers through the SCIM Playground
- Familiar Laravel tooling and middleware integration
- Fully extensible configuration for resources, attributes, and filtering
- Ships with dockerized demo and an expressive test suite

## Table of contents
- [Quick start](#quick-start)
- [Installation](#installation)
- [SCIM routes](#scim-routes)
- [Configuration](#configuration)
- [Security & app integration](#security--app-integration)
- [Test server](#test-server)
- [Contributing & support](#contributing--support)

## Quick start
Spin up a SCIM test server in seconds:

```bash
docker run -d -p 8000:8000 --name laravel-scim-server ghcr.io/limosa-io/laravel-scim-server:latest
```

Visit `http://localhost:8000/scim/v2/Users` (or `/Groups`, `/Schemas`, `/ResourceTypes`, etc.) to exercise the API.

## Installation
Add the package to your Laravel app:

```bash
composer require arietimmerman/laravel-scim-server
```

Optionally publish the config for fine-grained control:

```bash
php artisan vendor:publish --tag=laravel-scim
```

If you need to add SCIM-specific columns (`formatted`, `active`, `roles`) to your users table, publish the migrations:

```bash
php artisan vendor:publish --tag=laravel-scim-migrations
php artisan migrate
```

**Note:** These migrations are optional. Only publish them if your SCIM implementation requires these specific fields in your users table.

## SCIM routes

| Method | Path | Description |
|--------|------|-------------|
| GET | /scim/v1 | SCIM 1.x compatibility message (returns error with upgrade guidance) |
| GET | /scim/v2 | Cross-resource index (alias of `/scim/v2/`) |
| GET | /scim/v2/ | Cross-resource index |
| POST | /scim/v2/.search | Cross-resource search across all types |
| POST | /scim/v2/Bulk | SCIM bulk operations |
| GET | /scim/v2/ResourceTypes | List available resource types |
| GET | /scim/v2/ResourceTypes/{id} | Retrieve a specific resource type |
| GET | /scim/v2/Schemas | List SCIM schemas |
| GET | /scim/v2/Schemas/{id} | Retrieve a specific schema |
| GET | /scim/v2/ServiceProviderConfig | Discover server capabilities |
| GET | /scim/v2/{resourceType} | List resources of a given type |
| POST | /scim/v2/{resourceType} | Create a new resource |
| POST | /scim/v2/{resourceType}/.search | Filter resources of a given type |
| GET | /scim/v2/{resourceType}/{resourceObject} | Retrieve a single resource |
| PUT | /scim/v2/{resourceType}/{resourceObject} | Replace a resource |
| PATCH | /scim/v2/{resourceType}/{resourceObject} | Update a resource |
| DELETE | /scim/v2/{resourceType}/{resourceObject} | Delete a resource |

Optional "Me" routes can be enabled separately:

| Method | Path | Description |
|--------|------|-------------|
| GET | /scim/v2/Me | Retrieve the SCIM resource for the authenticated subject |
| PUT | /scim/v2/Me | Replace the SCIM resource for the authenticated subject |
| POST | /scim/v2/Me | Create the authenticated subject (requires `RouteProvider::meRoutePost()`) |

## Configuration

### Route Configuration

The SCIM server routes can be customized using the following configuration options in `config/scim.php`:

```php
return [
    // Base path for all SCIM routes
    'path' => env('SCIM_BASE_PATH', '/scim'),

    // Optional domain to restrict SCIM endpoints to
    'domain' => env('SCIM_DOMAIN', null),

    // Middleware for protected routes (resource operations)
    'middleware' => env('SCIM_MIDDLEWARE', []),

    // Middleware for public routes (ServiceProviderConfig, Schemas, ResourceTypes)
    'public_middleware' => env('SCIM_PUBLIC_MIDDLEWARE', []),
];
```

You can override these in your `.env` file:

```
SCIM_BASE_PATH=/scim/api
SCIM_MIDDLEWARE=api,auth:sanctum
SCIM_PUBLIC_MIDDLEWARE=api
```

If you need more control, you can disable route auto-publishing and register the routes manually:

```php
// config/scim.php
return [
    'publish_routes' => false,
    // other config...
];

// In your RouteServiceProvider or a custom service provider:
ArieTimmerman\Laravel\SCIMServer\RouteProvider::routes([
    'path' => '/custom-scim',
    'domain' => 'api.example.com',
    'middleware' => ['api', 'auth:api', 'scoped-tokens'],
    'public_middleware' => ['api', 'rate-limit'],
]);
```

### Resource Configuration

The package resolves configuration via `SCIMConfig::class`. Extend it to tweak resource definitions, attribute mappings, filters, or pagination defaults.

Register your custom config in `app/Providers/AppServiceProvider.php`:

```php
$this->app->singleton(
    \ArieTimmerman\Laravel\SCIMServer\SCIMConfig::class,
    YourCustomSCIMConfig::class
);
```

Minimal override example:

```php
<?php

class YourCustomSCIMConfig extends \ArieTimmerman\Laravel\SCIMServer\SCIMConfig
{
    public function getUserConfig()
    {
        $config = parent::getUserConfig();

        // Customize $config as needed.

        return $config;
    }
}
```

### Pagination settings
Cursor-based pagination is enabled by default via the [SCIM cursor pagination draft](https://datatracker.ietf.org/doc/draft-ietf-scim-cursor-pagination/). Publish the config file and update `config/scim.php` to adjust defaults:

```php
'pagination' => [
    'defaultPageSize' => 10,
    'maxPageSize' => 100,
    'cursorPaginationEnabled' => false,
]
```

## Security & app integration
SCIM grants the ability to view, add, update, and delete users or groups. Make sure you secure the routes before shipping to production.

You have two approaches to securing your SCIM endpoints:

### Option 1: Configure middleware via config
The simplest approach is to set middleware in your config:

```php
// config/scim.php
return [
    'middleware' => ['api', 'auth:sanctum'], // For protected resource routes
    'public_middleware' => ['api'],          // For schema/discovery endpoints
];
```

Or via environment variables:
```
SCIM_MIDDLEWARE=api,auth:sanctum
SCIM_PUBLIC_MIDDLEWARE=api
```

### Option 2: Manual route registration
For more control, disable automatic route publishing:

```php
// config/scim.php
return [
    'publish_routes' => false,
];
```

Then re-register the routes with your preferred middleware and configuration:

```php
use ArieTimmerman\Laravel\SCIMServer\RouteProvider as SCIMServerRouteProvider;

SCIMServerRouteProvider::publicRoutes([
    'path' => '/scim',
    'middleware' => ['api'],
]);

Route::middleware('auth:api')->group(function () {
    SCIMServerRouteProvider::routes([
        'path' => '/scim',
        'middleware' => ['custom-scim-check'],
        'public_routes' => false,
    ]);

    SCIMServerRouteProvider::meRoutes();
});
```

## Test server
Bring up the full demo stack with Docker Compose:

```bash
docker-compose up
```

Browse to `http://localhost:18123/scim/v2/Users` to explore the API and run the test suite.

## Contributing & support
- Issues and pull requests are welcome on [GitHub](https://github.com/arietimmerman/laravel-scim-server)
- Found this package helpful? [Give it a star on GitHub](https://github.com/arietimmerman/laravel-scim-server) so others can discover it faster
