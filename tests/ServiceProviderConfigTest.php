<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

class ServiceProviderConfigTest extends TestCase
{

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton('ArieTimmerman\Laravel\SCIMServer\SCIMConfig', CustomSCIMConfigSchema::class);
    }

    public function testGet()
    {
        $response = $this->get('/scim/v2/ServiceProviderConfig');
        $response->assertStatus(200);
    }

}
