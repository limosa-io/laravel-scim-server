<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\ServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class BaseTest extends TestCase
{
    use RefreshDatabase;

    protected $baseUrl = 'http://localhost';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations('testbench');

        $this->withFactories(realpath(dirname(__DIR__) . '/database/factories'));

        \ArieTimmerman\Laravel\SCIMServer\RouteProvider::routes();

        factory(\ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class, 100)->create();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app ['config']->set('app.url', 'http://localhost');
        $app ['config']->set('app.debug', true);

        $app->register(ServiceProvider::class);

        // Setup default database to use sqlite :memory:
        $app['config']->set('scimserver.Users.class', \ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class);
        $app['config']->set('auth.providers.users.model', \ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class);
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

}