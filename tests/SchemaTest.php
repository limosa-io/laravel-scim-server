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
    }

    public function getSchemaGenerator(){
        // generateSchema
    }
}
