
![](https://github.com/arietimmerman/laravel-scim-server/workflows/CI/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/arietimmerman/laravel-scim-server/v/stable)](https://packagist.org/packages/arietimmerman/laravel-scim-server)
[![Total Downloads](https://poser.pugx.org/arietimmerman/laravel-scim-server/downloads)](https://packagist.org/packages/arietimmerman/laravel-scim-server)

# SCIM 2.0 Server implementation for Laravel

Add SCIM 2.0 Server capabilities with ease. Usually, no configuration is needed in order to benefit from the basic functionalities.

~~~
composer require arietimmerman/laravel-scim-server
~~~

And optionally

~~~
php artisan vendor:publish --tag=laravel-scim
~~~

The module is used by [idaas.nl](https://www.idaas.nl/) and by [The SCIM Playground](https://scim.dev). 

# Routes

~~~
+----------+-----------------------------------------+
| GET|HEAD | scim/v1                                 |
| GET|HEAD | scim/v1/{fallbackPlaceholder}           |
| POST     | scim/v2/.search                         |
|          |                                         |
| GET|HEAD | scim/v2/ResourceTypes                   |
| GET|HEAD | scim/v2/ResourceTypes/{id}              |
| GET|HEAD | scim/v2/Schemas                         |
| GET|HEAD | scim/v2/Schemas/{id}                    |
| GET|HEAD | scim/v2/ServiceProviderConfig           |
| GET|HEAD | scim/v2/{fallbackPlaceholder}           |
|          |                                         |
| GET|HEAD | scim/v2/{resourceType}                  |
|          |                                         |
| POST     | scim/v2/{resourceType}                  |
|          |                                         |
| GET|HEAD | scim/v2/{resourceType}/{resourceObject} |
|          |                                         |
| PUT      | scim/v2/{resourceType}/{resourceObject} |
|          |                                         |
| PATCH    | scim/v2/{resourceType}/{resourceObject} |
|          |                                         |
| DELETE   | scim/v2/{resourceType}/{resourceObject} |
|          |                                         |
+----------+-----------------------------------------+
~~~

# Configuration

The configuration is retrieved from `SCIMConfig::class`.

Extend this class and register your extension in `app/Providers/AppServiceProvider.php` like this.

~~~.php
$this->app->singleton('ArieTimmerman\Laravel\SCIMServer\SCIMConfig', YourCustomSCIMConfig::class);
~~~

## An example override

Here's one way to override the default configuration without copying too much of the SCIMConfig file into your app.
~~~.php
<?php

class YourCustomSCIMConfig extends \ArieTimmerman\Laravel\SCIMServer\SCIMConfig
{
    public function getUserConfig()
    {
        $config = parent::getUserConfig();

        // Modify the $config variable however you need...

        return $config;
    }
}
~~~


# Security & App Integration

By default, this package does no security checks on its own. This can be dangerous, in that a functioning SCIM Server can view, add, update, delete, or list users. 
You are welcome to implement your own security checks at the middleware layer, 
or somehow/somewhere else that makes sense for your application. But make sure to do **something**.

If you want to integrate into _already existing_ middleware, you'll want to take the following steps - 

## Turn off automatic publishing of routes

Modify `config/scim.php` like this:
```php
<?php
return [
    "publish_routes" => false
];
```

## Next, explicitly publish your routes with your choice of middleware

In either your RouteServiceProvider, or in a particular route file, add the following:

```php
use ArieTimmerman\Laravel\SCIMServer\RouteProvider as SCIMServerRouteProvider;

SCIMServerRouteProvider::publicRoutes(); // Make sure to add public routes *first*


Route::middleware('auth:api')->group(function () { // or any other middleware you choose
    SCIMServerRouteProvider::routes(
        [
            'public_routes' => false // but do not hide public routes (metadata) behind authentication
        ]
    );

    SCIMServerRouteProvider::meRoutes();
});


```

# Test server

~~~
docker-compose up
~~~

Now visit `http://localhost:18123/scim/v2/Users`.
