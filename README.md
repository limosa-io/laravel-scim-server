
# SCIM 2.0 Server implementation for Laravel

Add SCIM 2.0 Server capabilities with ease.

~~~

~~~

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

* Better support for Create requests
* Filtering with arrays of complex attributes
* emit events for all SCIM actions, with resulting laravel models
* Deal with the limitations of SCIM, including
	* Error handling. Very limited. What if two fields are wrong?
	* Yet another schema. Please user JSON Schema.
	* ServiceDefinition is very, very limited
	* Instead of /ResourceObjects and /Schemas, a raml/openapi definition would perhaps be better

