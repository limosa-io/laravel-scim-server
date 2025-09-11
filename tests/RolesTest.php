<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RolesTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // alter table, add json column "roles"
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable();
        });
    }

    public function testCreate()
    {
        $response = $this->post('/scim/v2/Users', [
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
            ],
            "urn:ietf:params:scim:schemas:core:2.0:User" => [
                "userName" => "Dr. John Doe",
                "password" => "password",
                "emails" => [
                    [
                        "value" => "john@exampl.com",
                        "type" => "work"
                    ]
                ],
                "roles" => [
                    [
                        "value" => "admin",
                        "display" => "Administrator",
                    ],
                    [
                        "value" => "user",
                        "display" => "Users",
                    ]
                ]
            ]
        ]);

        $response->assertStatus(201);
    }


    public function testPatch()
    {
        $response = $this->patch('/scim/v2/Users/2', [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [[
                "op" => "add",
                "path" => "roles",
                "value" => [
                    [
                      "value" => "admin",
                      "display" => "Administrator",
                    ]
                ]
            ]]
        ]);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertArrayHasKey('roles', $json['urn:ietf:params:scim:schemas:core:2.0:User']);
        $this->assertCount(1, $json['urn:ietf:params:scim:schemas:core:2.0:User']['roles']);
        $this->assertEquals('admin', $json['urn:ietf:params:scim:schemas:core:2.0:User']['roles'][0]['value']);
        $this->assertEquals('Administrator', $json['urn:ietf:params:scim:schemas:core:2.0:User']['roles'][0]['display']);
    }

    public function testPut()
    {
        $response = $this->put('/scim/v2/Users/1', [
            "id" => "1",
            "meta" => [
                "resourceType" => "User",
                "created" => "2010-01-23T04:56:22Z",
                "lastModified" => "2011-05-13T04:42:34Z",
                "version" => "W\/\"3694e05e9dff594\""
            ],
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
            ],
            "urn:ietf:params:scim:schemas:core:2.0:User" => [
                "userName" => "Dr. John Doe",
                "emails" => [
                    [
                        "value" => "johndoe@bailey.org",
                        "type" => "other",
                        "primary" => true
                    ]
                ],
                "groups" => [
                    [
                        "value" => "1"
                    ]
                    ],
                "roles" => [
                    [
                        "value" => "admin",
                        "display" => "Administrator",
                    ],
                    [
                        "value" => "user",
                        "display" => "Users",
                    ]
                ]
            ]
        ]);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertArrayHasKey('roles', $json['urn:ietf:params:scim:schemas:core:2.0:User']);
        $this->assertCount(2, $json['urn:ietf:params:scim:schemas:core:2.0:User']['roles']);
        $this->assertEquals('admin', $json['urn:ietf:params:scim:schemas:core:2.0:User']['roles'][0]['value']);
        $this->assertEquals('Administrator', $json['urn:ietf:params:scim:schemas:core:2.0:User']['roles'][0]['display']);

        // find role with value admin
        $response = $this->get('/scim/v2/Users?filter=roles.value sw "adm"');
        $response->assertStatus(200);
        $json = $response->json();
        $this->assertCount(1, $json['Resources']);

        $response = $this->patch('/scim/v2/Users/1', [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [[
                "op" => "remove",
                "path" => "roles",
                "value" => [
                    [
                      "value" => "admin",
                      "display" => "Administrator",
                    ]
                ]
            ]]
        ]);

        $json = $response->json();

        $this->assertCount(1, $json['urn:ietf:params:scim:schemas:core:2.0:User']['roles']);
        $this->assertEquals('user', $json['urn:ietf:params:scim:schemas:core:2.0:User']['roles'][0]['value']);
    }
}
