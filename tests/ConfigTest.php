<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\Role;
use Illuminate\Support\Arr;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ConfigTest extends TestCase
{
   

    protected function setUp(): void
    {
        parent::setUp();

        // set scim.omit_null_values to true
        
    }

    protected function createUser(){
        return $this->post('/scim/v2/Users', [
            // "id" => 1,
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
            ],
            "urn:ietf:params:scim:schemas:core:2.0:User" => [
                "userName" => "Dr. Marie Jo",
                "password" => "Password123",
                "emails" => [
                    [
                        "value" => "mariejo@example.com",
                        "type" => "primary",
                        "primary" => true
                    ]
                ]
            ]
        ]);
    }

    public function testOmitMainSchema()
    {
        config(['scim.omit_main_schema_in_return' => true]);
        // create user with post
        $response = $this->createUser();

        $this->assertEquals(
            201,
            $response->baseResponse->getStatusCode(),
            'Wrong status: ' . $response->baseResponse->content()
        );

        $this->assertArrayNotHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $response->json());
    }

    public function testDoNotOmitMainSchema()
    {
        config(['scim.omit_main_schema_in_return' => false]);
        // create user with post
        $response = $this->createUser();

        $this->assertEquals(
            201,
            $response->baseResponse->getStatusCode(),
            'Wrong status: ' . $response->baseResponse->content()
        );

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $response->json());
    }

    public function testDoNotOmitNullValues()
    {
        config(['scim.omit_null_values' => false]);
        // create user with post
        $response = $this->createUser();

        $this->assertEquals(
            201,
            $response->baseResponse->getStatusCode(),
            'Wrong status: ' . $response->baseResponse->content()
        );

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $response->json());

        $expected = [
                "employeeNumber" => null
            
        ];

        $this->assertEquals($expected, Arr::get($response->json(), 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User'));
    }

    public function testOmitNullValues()
    {
        config(['scim.omit_null_values' => true]);
        // create user with post
        $response = $this->createUser();

        $this->assertEquals(
            201,
            $response->baseResponse->getStatusCode(),
            'Wrong status: ' . $response->baseResponse->content()
        );

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $response->json());

        // null because of scim.omit_null_values set to true
        $expected = null;
        $this->assertEquals($expected, Arr::get($response->json(), 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User'));
    }

}
