<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\ServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasicTest extends TestCase
{
    protected $baseUrl = 'http://localhost';
    
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadLaravelMigrations('testbench');
        
        $this->withFactories(realpath(dirname(__DIR__).'/database/factories'));
        
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
    
    public function testGet()
    {
        $response = $this->get('/scim/v2/Users');
                
        $response->assertStatus(200);
    }

    public function testPut()
    {
        $response = $this->put('/scim/v2/Users/1', [
            "id"=> 1,
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
            ],
            "urn:ietf:params:scim:schemas:core:2.0:User" => [
                "userName"=> "Dr. John Doe",
                "emails"=> [
                    [
                        "value"=> "johndoe@bailey.org",
                        "type"=> "other",
                        "primary"=> true
                    ]
                ]
            ]
        ]);
                
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('johndoe@bailey.org', $json['urn:ietf:params:scim:schemas:core:2.0:User']['emails'][0]['value']);
        $this->assertEquals('Dr. John Doe', $json['urn:ietf:params:scim:schemas:core:2.0:User']['userName']);
    }

    public function testPatch()
    {
        $response = $this->patch('/scim/v2/Users/2', [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [[
                "op" => "replace",
                "value" => [
                  "emails" => [
                    [
                      "value" => "something@example.com",
                      "type" => "work",
                      "primary" => true
                    ]
                  ]
                ]
            ]]
        ]);
                
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('something@example.com', $json['urn:ietf:params:scim:schemas:core:2.0:User']['emails'][0]['value']);
    }

    public function testDelete()
    {
        $response = $this->delete('/scim/v2/Users/1');
        $response->assertStatus(204);
    }

    public function testPost()
    {
        $response = $this->post('/scim/v2/Users', [
            "id"=> 1,
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
            ],
            "urn:ietf:params:scim:schemas:core:2.0:User" => [
                "userName"=> "Dr. Marie Jo",
                "password"=>"Password123",
                "emails"=> [
                    [
                        "value"=> "mariejo@example.com",
                        "type"=> "primary",
                        "primary"=> true
                    ]
                ]
            ]
        ]);
        
        $this->assertEquals(
            201,
            $response->baseResponse->getStatusCode(),
            'Wrong status: ' . $response->baseResponse->content()
        );

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('mariejo@example.com', $json['urn:ietf:params:scim:schemas:core:2.0:User']['emails'][0]['value']);
        $this->assertEquals('Dr. Marie Jo', $json['urn:ietf:params:scim:schemas:core:2.0:User']['userName']);
    }
}
