<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CustomSCIMConfig extends SCIMConfig
{
    public function getUserConfig()
    {
        $config = parent::getUserConfig();

        $config['schemas'][] = 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User';
        $config['validations']['urn:ietf:params:scim:schemas:extension:enterprise:2\.0:User:employeeNumber'] = 'nullable';

        $config['mapping']['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User'] = [
            'employeeNumber' => new Attribute("employeeNumber")
        ];

        return $config;
    }
}

class CustomSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('users', function (Blueprint $table) {
            $table->string('employeeNumber')->nullable();
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton('ArieTimmerman\Laravel\SCIMServer\SCIMConfig', CustomSCIMConfig::class);
    }

    public function testPost()
    {
        $response = $this->post('/scim/v2/Users', [
            "id" => 1,
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
                'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User'
            ],
            "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User" => [
                'employeeNumber' => '123'
            ],
            "urn:ietf:params:scim:schemas:core:2.0:User" => [
                "userName" => "Dr. Marie Jo",
                "password" => "Password123",
                'employeeNumber' => '123',
                "emails" => [
                    [
                        "value" => "mariejo@example.com",
                        "type" => "primary",
                        "primary" => true
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
        $this->assertEquals('123', $json['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User']['employeeNumber']);
    }
}
