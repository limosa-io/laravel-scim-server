<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

class HeaderTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->singleton('ArieTimmerman\Laravel\SCIMServer\SCIMConfig', CustomSCIMConfigSchema::class);
    }

    public function testGet()
    {
        $response = $this->get('/scim/v2/Users/1');
        $response->assertStatus(200);
        $response->assertHeader('ETag');
        $response->assertJsonStructure(
            [
            'meta' => [
                'created',
                'lastModified',
                'location',
                'resourceType',
                'version'
            ],
            ]
        );
        // get the value of version from the response content
        $version = $response->baseResponse->original['meta']['version'];
        $this->assertStringStartsWith('W/', $version);
        $etag = $response->baseResponse->headers->get('ETag');
        $this->assertStringStartsWith('W/', $etag);
        $this->assertEquals($etag, $version);
    }


    public function testPut(){
        $response = $this->put('/scim/v2/Users/1', [
            'userName' => 'newUserName'
        ], ['IF-MATCH' => 'W/"1"']);
        $response->assertStatus(412);

        // first retrieve the version by sending a get request
        $response = $this->get('/scim/v2/Users/1');
        $response->assertStatus(200);
        $etag = $response->baseResponse->headers->get('ETag');

        // now update it 
        $response = $this->put('/scim/v2/Users/1', [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'userName' => 'newUserName',
            'id' => '1',
        ], ['If-Match' => $etag]);
        $response->assertStatus(200);
    }
}
