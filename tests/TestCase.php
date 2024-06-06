<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $baseUrl = 'http://localhost';

    protected $headers = [
        'host' => 'localhost',
        'user-agent' => 'Symfony',
        'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'accept-language' => 'en-us,en;q=0.5',
        'accept-charset' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
        'content-type' => 'application/scim+json',
    ];

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