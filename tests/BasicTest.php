<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

class BasicTest extends TestCase
{
    public function testGet()
    {
        $response = $this->get('/scim/v2/Users', $this->headers);

        $response->assertStatus(200);
    }

    public function testPut()
    {
        $response = $this->putJson('/scim/v2/Users/1', [
            "id" => 1,
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
                ]
            ]
        ], $this->headers);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('johndoe@bailey.org', $json['urn:ietf:params:scim:schemas:core:2.0:User']['emails'][0]['value']);
        $this->assertEquals('Dr. John Doe', $json['urn:ietf:params:scim:schemas:core:2.0:User']['userName']);
    }

    public function testPatch()
    {
        $response = $this->patchJson('/scim/v2/Users/2', [
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
        ], $this->headers);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('something@example.com', $json['urn:ietf:params:scim:schemas:core:2.0:User']['emails'][0]['value']);
    }

    public function testDelete()
    {
        $response = $this->deleteJson('/scim/v2/Users/1', [], $this->headers);
        $response->assertStatus(204);
    }

    public function testPost()
    {
        $response = $this->postJson('/scim/v2/Users', [
            "id" => 1,
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
        ], $this->headers);

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
