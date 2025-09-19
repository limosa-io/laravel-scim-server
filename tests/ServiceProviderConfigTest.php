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
        $this->assertTrue($response->json('pagination.cursor'));
        $this->assertSame(3600, $response->json('pagination.cursorTimeout'));
    }

    public function testCursorPaginationCanBeDisabled()
    {
        config(['scim.pagination.cursorPaginationEnabled' => false]);

        try {
            $response = $this->get('/scim/v2/ServiceProviderConfig');
            $response->assertStatus(200);
            $this->assertFalse($response->json('pagination.cursor'));
            $this->assertArrayNotHasKey('cursorTimeout', $response->json('pagination') ?? []);
        } finally {
            config(['scim.pagination.cursorPaginationEnabled' => true]);
        }
    }

}
