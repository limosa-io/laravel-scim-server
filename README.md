
![](https://github.com/arietimmerman/laravel-scim-server/workflows/CI/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/arietimmerman/laravel-scim-server/v/stable)](https://packagist.org/packages/arietimmerman/laravel-scim-server)
[![Total Downloads](https://poser.pugx.org/arietimmerman/laravel-scim-server/downloads)](https://packagist.org/packages/arietimmerman/laravel-scim-server)

# SCIM 2.0 Server implementation for Laravel

Add SCIM 2.0 Server capabilities with ease. Usually, no configuration is needed in order to benefit from the basic functionalities.

~~~
composer require arietimmerman/laravel-scim-server
~~~

The module is used by [idaas.nl](https://www.idaas.nl/).

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

# Test server

~~~
docker-compose up
~~~

Now visit `http://localhost:18123/scim/v2/Users`.
