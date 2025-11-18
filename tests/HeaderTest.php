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

    public function testLocationHeaderOnlyReturnedOnSuccessfulCreate()
    {
        $createResponse = $this->post('/scim/v2/Users', $this->validUserPayload());
        $createResponse->assertStatus(201);
        $createResponse->assertHeader('Location');

        $locationHeader = $createResponse->baseResponse->headers->get('Location');
        $this->assertMatchesRegularExpression('#^http://localhost/scim/v2/Users/\d+$#', $locationHeader);

        $userId = $createResponse->json('id');
        $this->assertNotEmpty($userId, 'Created SCIM resource is missing an id');

        $getResponse = $this->get("/scim/v2/Users/{$userId}");
        $getResponse->assertStatus(200);
        $getResponse->assertHeaderMissing('Location');
        $etag = $getResponse->baseResponse->headers->get('ETag');
        $this->assertNotEmpty($etag, 'GET response should expose an ETag for optimistic locking');

        $putResponse = $this->put(
            "/scim/v2/Users/{$userId}",
            [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                'userName' => 'location-put-' . uniqid(),
                'id' => (string) $userId,
            ],
            ['If-Match' => $etag]
        );
        $putResponse->assertStatus(200);
        $putResponse->assertHeaderMissing('Location');

        $patchResponse = $this->patch("/scim/v2/Users/{$userId}", [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
            'Operations' => [[
                'op' => 'add',
                'path' => 'userName',
                'value' => 'location-patch-' . uniqid(),
            ]],
        ]);
        $patchResponse->assertStatus(200);
        $patchResponse->assertHeaderMissing('Location');

        $deleteResponse = $this->delete("/scim/v2/Users/{$userId}");
        $deleteResponse->assertStatus(204);
        $deleteResponse->assertHeaderMissing('Location');
    }

    protected function validUserPayload(?string $email = null): array
    {
        $email = $email ?? sprintf('location.%s@example.test', uniqid());

        return [
            'schemas' => [
                'urn:ietf:params:scim:schemas:core:2.0:User',
            ],
            'urn:ietf:params:scim:schemas:core:2.0:User' => [
                'userName' => 'location-user-' . uniqid(),
                'password' => 'Password123',
                'emails' => [[
                    'value' => $email,
                    'type' => 'work',
                    'primary' => true,
                ]],
            ],
        ];
    }
}
