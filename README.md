
# SCIM 2.0 Server implementation for Laravel

Add SCIM 2.0 Server capabilities with ease.

~~~

~~~

# Optional


Publish the config file.

~~~
php artisan vendor:publish --provider="ArieTimmerman\Laravel\SCIMServer\ServiceProvider"
~~~

# TODO

* How to do validation?
* emit events for all SCIM actions, with resulting laravel models
* Deal with the limitations of SCIM, including
	* Error handling. Very limited. What if two fields are wrong?
	* Yet another schema. Please user JSON Schema.
	* ServiceDefinition is very, very limited
	* Instead of /ResourceObjects and /Schemas, a raml/openapi definition would perhaps be better

