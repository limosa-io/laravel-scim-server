<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

class ResourceTypesTest extends TestCase
{

    #[\Override]
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton(\ArieTimmerman\Laravel\SCIMServer\SCIMConfig::class, CustomSCIMConfigSchema::class);
    }

    public function testGet()
    {
        $response = $this->get('/scim/v2/ResourceTypes');
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'schemas',
            'totalResults',
            'Resources' => [
                '*' => [
                    'schemas',
                    'id',
                    'name',
                    'endpoint',
                    'description',
                    'schema',
                    'schemaExtensions',
                    'meta' => [
                        'location',
                        'resourceType'
                    ]
                ]
            ]
        ]);
    }

    public function testGetOne(){
        $response = $this->get('/scim/v2/ResourceTypes/User');
        $response->assertJson([
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:ResourceType"
            ],
            "id" => "User",
            "name" => "User",
            "endpoint" => "http://localhost/scim/v2/Users",
            "description" => "User Account",
            "schema" => "urn:ietf:params:scim:schemas:core:2.0:User",
            "schemaExtensions" => [
                [
                    "schema" => "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User",
                    "required" => false
                ]
            ],
            "meta" => [
                "location" => "http://localhost/scim/v2/ResourceTypes/User",
                "resourceType" => "ResourceType"
            ]
        ]);

        $response->assertStatus(200);
    }

}
