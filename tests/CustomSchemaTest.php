<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\User;
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
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton('ArieTimmerman\Laravel\SCIMServer\SCIMConfig', CustomSCIMConfig::class);
    }

    public function testPost()
    {
        $response = $this->post('/scim/v2/Users', [
            // "id" => 1,
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

    public function testPatchEnterpriseSchema()
    {
        $response = $this->patch('/scim/v2/Users/2', [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [
                [
                    "op" => "replace",
                    "path" => "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User:employeeNumber",
                    "value" => "12345"
                ]
            ]
        ]);


        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:extension:enterprise:2.0:User', $json);
        $this->assertEquals('12345', $json['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User']['employeeNumber']);
    }

    public function testPatchAddEnterpriseSchemaWithValuePayload()
    {
        $response = $this->patch('/scim/v2/Users/2', [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [
                [
                    "op" => "add",
                    "value" => [
                        "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User:employeeNumber" => "12345",
                    ]
                ]
            ]
        ]);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('12345', $json['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User']['employeeNumber']);
        $this->assertEquals('12345', User::find(2)->employeeNumber);
    }

    /**
     * Regression test for https://github.com/limosa-io/laravel-scim-server/issues/168
     *
     * POST /scim/v2/Users must return 201 (not 500) when the SCIM payload contains
     * sub-attributes inside an extension schema that are not defined in the resource
     * mapping (e.g. Entra ID sends manager.displayName / manager.$ref even when the
     * mapping only covers employeeNumber).
     */
    public function testPostWithUnmappedEnterpriseSubAttributesReturns201()
    {
        $response = $this->postJson('/scim/v2/Users', [
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
                "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User",
            ],
            "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User" => [
                // employeeNumber IS mapped; manager and all its sub-attributes are NOT
                "employeeNumber" => "EMP-001",
                "manager" => [
                    "value"       => "1234",
                    "displayName" => "Some Manager",
                    '$ref'        => "https://example.com/scim/v2/Users/1234",
                ],
                "costCenter"   => "CC-42",
                "organization" => "ACME Corp",
                "division"     => "Engineering",
            ],
            "urn:ietf:params:scim:schemas:core:2.0:User" => [
                "userName" => "jane.doe@example.com",
                "emails"   => [
                    [
                        "value"   => "jane.doe@example.com",
                        "type"    => "other",
                        "primary" => true,
                    ],
                ],
            ],
        ]);

        $this->assertEquals(
            201,
            $response->baseResponse->getStatusCode(),
            'Expected 201 but got ' . $response->baseResponse->getStatusCode() . ': ' . $response->baseResponse->content()
        );

        $json = $response->json();

        // The mapped attribute should be persisted
        $this->assertEquals(
            'EMP-001',
            $json['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User']['employeeNumber']
        );
    }
}
