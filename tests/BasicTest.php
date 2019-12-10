<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasicTest extends TestCase
{
    protected $baseUrl = 'http://localhost';
    
    use RefreshDatabase;
    
    
    
    public function setUp()
    {
        parent::setUp();
        
        $this->loadLaravelMigrations('testbench');
        
        $this->withFactories(realpath(dirname(__DIR__).'/database/factories'));
        
        \ArieTimmerman\Laravel\SCIMServer\RouteProvider::routes();
        ;
        
        factory(\ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class, 100)->create();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app ['config']->set('app.url', 'http://localhost');
        ;
                
        // Setup default database to use sqlite :memory:
        
        $app['config']->set('scimserver.Users.class', \ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class);
        
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
    
    public function testGet()
    {
        $response = $this->get('/scim/v2/Users');
        
        
        $response->assertStatus(200);
    }
}
