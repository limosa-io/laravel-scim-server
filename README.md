
![](https://github.com/arietimmerman/laravel-scim-server/workflows/CI/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/arietimmerman/laravel-scim-server/v/stable)](https://packagist.org/packages/arietimmerman/laravel-scim-server)
[![Total Downloads](https://poser.pugx.org/arietimmerman/laravel-scim-server/downloads)](https://packagist.org/packages/arietimmerman/laravel-scim-server)

# SCIM 2.0 Server implementation for Laravel

Add SCIM 2.0 Server capabilities with ease. Usually, no configuration is needed in order to benefit from the basic functionalities.

~~~
composer require arietimmerman/laravel-scim-server
~~~

The module is used by [idaas.nl](https://www.idaas.nl/).

# Test server

~~~
docker-compose up
~~~

Now visit `http://localhost:18123/scim/v2/Users`.
