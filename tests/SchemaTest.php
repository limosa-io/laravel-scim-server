<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;

class CustomSCIMConfigSchema extends SCIMConfig
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


class SchemaTest extends TestCase
{

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton('ArieTimmerman\Laravel\SCIMServer\SCIMConfig', CustomSCIMConfigSchema::class);
    }

    public function testGet()
    {
        $response = $this->get('/scim/v2/Schemas');
        $response->assertStatus(200);

        $jsonResponse = $response->json();
        $this->assertNotEmpty($jsonResponse);

        // Find the User schema
        $userSchema = null;
        foreach ($jsonResponse['Resources'] as $schema) {
            if ($schema['id'] === 'urn:ietf:params:scim:schemas:core:2.0:User') {
                $userSchema = $schema;
                break;
            }
        }

        $this->assertNotNull($userSchema, "User schema not found");

        // Find the active attribute in the schema attributes
        $activeAttribute = null;
        foreach ($userSchema['attributes'] as $attribute) {
            if ($attribute['name'] === 'active') {
                $activeAttribute = $attribute;
                break;
            }
        }

        $this->assertNotNull($activeAttribute, "Active attribute not found");
        $this->assertEquals('boolean', $activeAttribute['type'], "Active attribute is not of type boolean");

    }

    public function getSchemaGenerator(){
        // generateSchema
    }
}
