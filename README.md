
# SCIM 2.0 Server implementation for Laravel

Add SCIM 2.0 Server capabilities with ease. Usually, no configuration is needed in order to benefit from the basic functionalities.

~~~
composer require arietimmerman/laravel-scim-server
~~~

# Optional

Publish the config file.

~~~
php artisan vendor:publish --provider="ArieTimmerman\Laravel\SCIMServer\ServiceProvider"
~~~

# TODO

TODO for the next releases.

* How to do validation?
* Emit events for all SCIM actions, with resulting laravel models
* Deal with the limitations of SCIM, including
	* Error handling. Very limited. What if two fields are wrong?
	* Yet another schema. Please user JSON Schema.
	* ServiceDefinition is very, very limited
	* Instead of /ResourceObjects and /Schemas, a raml/openapi definition would perhaps be better


# Hoe registratie?

POST naar /Me. Create event opvangen. Als active=false dan een e-mail uitsturen met een access_token als id_token_hint

