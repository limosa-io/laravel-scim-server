
# SCIM 2.0 Server implementation for Laravel

__WORK IN PROGRESS. NOT FOR USE IN PRODUCTION ENVIRONMENTS.__

This Laravel package allows adding SCIM 2.0 server functionalities to existing Laravel projects.

# Usage

In your User model, add the following.

~~~.php

use ArieTimmerman\Laravel\SCIMServer\Traits\SCIMResource;

class User extends Authenticatable
{
    use SCIMResource;
    
    // [...]

}

~~~

Publish the config file.

~~~
php artisan vendor:publish --provider="ArieTimmerman\Laravel\SCIMServer\ServiceProvider"
~~~

# TODO

* Remove the need for any configuration
* Describe how to map attributes
* Create examples
* Support for PATCH and PUT requests
* Better support for Create requests
* Filtering with arrays of complex attributes
* Create database schemas for optional use for new SCIM 2.0 servers. This removes the need for mapping attributes.
* Deal with the limitations of SCIM, including
	* Error handling. Very limited. What if two fields are wrong?
	* Yet another schema. Please user JSON Schema.
	* Instead of /ResourceObjects and /Schemas, a raml/openapi definition would perhaps be better

