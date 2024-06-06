<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

class ErrorTest extends TestCase
{
    public function testHeader()
    {
        $response = $this->putJson('/scim/v2/Users/1', [
            "id" => 1,
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
            ],
            "urn:ietf:params:scim:schemas:core:2.0:User" => [
                "userName" => "Dr. John Smith",
                "emails" => [
                    [
                        "value" => "johnsmith@bailey.org",
                        "type" => "other",
                        "primary" => true
                    ]
                ]
            ]
        ], ['content-type' => 'invalid-content-type']);

        $response->assertStatus(400);

        $json = $response->json();

        $this->assertEquals('urn:ietf:params:scim:api:messages:2.0:Error', $json['schemas'][0]);
        $this->assertEquals('The content-type header should be set to "application/scim+json"', $json['detail']);
        $this->assertEquals('invalidValue', $json['scimType']);
    }
}
