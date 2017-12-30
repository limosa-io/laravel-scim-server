<?php
/**
 * Laravel service provider for registering the routes and publishing the configuration.
 */

namespace ArieTimmerman\Laravel\SCIMServer;

class ServiceProvider extends \Illuminate\Support\ServiceProvider{
	
	public function boot(\Illuminate\Routing\Router $router) {
		
		$this->publishes([
				__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'scimserver.php' => config_path('scimserver.php'),
		]);
				
		$this->loadMigrationsFrom(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
		
		$this->loadViewsFrom(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'views','scimserver');
		
		$this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
		
		$this->publishes([
				__DIR__.'/../views' => resource_path('views/vendor/scimserver'),
		]);

		$router->middleware('SCIMHeaders', 'ArieTimmerman\Laravel\SCIMServer\Middleware\SCIMHeaders');
		
	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {
		
	}
	
}