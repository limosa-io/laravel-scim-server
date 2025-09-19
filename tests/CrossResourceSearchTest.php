<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

class CrossResourceSearchTest extends TestCase
{
    public function testIndexCombinesResourceTypes()
    {
        $response = $this->get('/scim/v2');

        $response->assertStatus(200);
        $response->assertJson([
            'totalResults' => 200,
            'itemsPerPage' => 10,
            'startIndex' => 1,
        ]);

        $resources = $response->json('Resources');

        $this->assertCount(10, $resources);
        $this->assertEquals('User', $resources[0]['meta']['resourceType']);
    }

    public function testIndexSupportsStartIndexAcrossResources()
    {
        $response = $this->get('/scim/v2?startIndex=101&count=5');

        $response->assertStatus(200);
        $response->assertJson([
            'totalResults' => 200,
            'itemsPerPage' => 5,
            'startIndex' => 101,
        ]);

        $resources = $response->json('Resources');

        $this->assertCount(5, $resources);
        $this->assertEquals('Group', $resources[0]['meta']['resourceType']);
    }

    public function testIndexReturnsUserAndGroup()
    {
        $response = $this->get('/scim/v2?startIndex=99&count=4');

        $response->assertStatus(200);

        $resources = collect($response->json('Resources'));

        $this->assertSame([
            'Group',
            'User',
        ], $resources->pluck('meta.resourceType')->unique()->sort()->values()->toArray());

        $this->assertTrue(
            $resources->contains(fn ($resource) =>
                ($resource['meta']['resourceType'] ?? null) === 'User'
                && ($resource['urn:ietf:params:scim:schemas:core:2.0:User']['userName'] ?? null) === 'boundary.user'
            ),
            'Boundary User not found in combined listing.'
        );

        $this->assertTrue(
            $resources->contains(fn ($resource) =>
                ($resource['meta']['resourceType'] ?? null) === 'Group'
                && ($resource['urn:ietf:params:scim:schemas:core:2.0:Group']['displayName'] ?? null) === 'Boundary Group'
            ),
            'Boundary Group not found in combined listing.'
        );
    }

    public function testIndexFilterRestrictsToUsers()
    {
        $response = $this->get('/scim/v2?filter=(meta.resourceType eq "User")');

        $response->assertStatus(200);

        $resources = collect($response->json('Resources'));

        $this->assertNotEmpty($resources);
        $this->assertSame(['User'], $resources->pluck('meta.resourceType')->unique()->values()->toArray());
    }

    public function testIndexFilterSupportsOrAcrossResourceTypes()
    {
        $responseFirstPage = $this->get('/scim/v2?count=10&filter=(meta.resourceType eq "User") or (meta.resourceType eq "Group")');

        $responseFirstPage->assertStatus(200);

        $resourcesFirstPage = collect($responseFirstPage->json('Resources'));

        $this->assertTrue($resourcesFirstPage->contains(fn ($resource) => ($resource['meta']['resourceType'] ?? null) === 'User'));
        $this->assertEmpty(array_diff($resourcesFirstPage->pluck('meta.resourceType')->unique()->values()->toArray(), ['User', 'Group']));

        $responseSecondPage = $this->get('/scim/v2?startIndex=101&count=5&filter=(meta.resourceType eq "User") or (meta.resourceType eq "Group")');

        $responseSecondPage->assertStatus(200);

        $resourcesSecondPage = collect($responseSecondPage->json('Resources'));

        $this->assertNotEmpty($resourcesSecondPage);
        $this->assertEquals('Group', $resourcesSecondPage->first()['meta']['resourceType']);
    }

    public function testIndexRejectsCursorParameter()
    {
        $response = $this->get('/scim/v2?cursor=opaque');

        $response->assertStatus(400);
        $response->assertJson([
            'scimType' => 'invalidCursor',
            'status' => '400',
        ]);
    }

    public function testPostSearchHonorsCountAndStartIndex()
    {
        $response = $this->postJson(
            '/scim/v2/.search',
            [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:SearchRequest'],
                'count' => 5,
                'startIndex' => 198,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([
            'totalResults' => 200,
            'startIndex' => 198,
        ]);

        $resources = $response->json('Resources');

        $this->assertCount(3, $resources);
        $this->assertSame(count($resources), $response->json('itemsPerPage'));
        $this->assertEquals('Group', $resources[0]['meta']['resourceType']);
    }
}
