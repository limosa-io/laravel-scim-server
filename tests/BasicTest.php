<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\Group;
use Illuminate\Support\Arr;

class BasicTest extends TestCase
{
    public function testGet()
    {
        $response = $this->get('/scim/v2/Users', $this->headers);

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'Resources');
        $response->assertJson([
            'totalResults' => 100,
            'itemsPerPage' => 10,
            'startIndex' => 1
        ]);

        $response->assertJsonStructure([
            'Resources' => [
                '*' => [
                    'id',
                    'schemas',
                    'meta',
                    'urn:ietf:params:scim:schemas:core:2.0:User' => [
                        'userName',
                        'name',
                        'emails',
                        'groups' => [
                            '*' => [
                                'value',
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function testGetAttributes()
    {
        $response = $this->get('/scim/v2/Users?attributes=userName,name.formatted,groups');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'Resources');
        $response->assertJsonStructure([
            'Resources' => [
                '*' => [
                    'urn:ietf:params:scim:schemas:core:2.0:User' => [
                        'userName',
                    ]
                ]
            ]
        ]);

        foreach ($response->json('Resources') as $resource) {
            $this->assertArrayNotHasKey('emails', $resource['urn:ietf:params:scim:schemas:core:2.0:User']);
        }
    }

    public function testGetAttributesSearch()
    {
        $response = $this->postJson(
            '/scim/v2/Users/.search',
            [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:SearchRequest'],
                'attributes' => [
                    'userName',
                    'name.formatted',
                    'groups'
                ]
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'Resources');
        $response->assertJsonStructure([
            'Resources' => [
                '*' => [
                    'urn:ietf:params:scim:schemas:core:2.0:User' => [
                        'userName',
                    ]
                ]
            ]
        ]);

        foreach ($response->json('Resources') as $resource) {
            $this->assertArrayNotHasKey('emails', $resource['urn:ietf:params:scim:schemas:core:2.0:User']);
        }
    }

    public function testGetGroupsAttribute()
    {
        config(['scim.omit_main_schema_in_return' => true]);
        $response = $this->get('/scim/v2/Users?attributes=groups');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'Resources');
        $response->assertJsonStructure([
            'Resources' => [
                '*' => [

                ]
            ]
        ]);
    }

    public function testCursorPagination()
    {
        $response1 = $this->get('/scim/v2/Users?count=60&cursor');

        $response1->assertStatus(200);
        $response1->assertJsonCount(60, 'Resources');
        $response1->assertJson([
            'totalResults' => 100,
            'itemsPerPage' => 60
        ]);

        $response2 = $this->get('/scim/v2/Users?count=60&cursor=' . $response1->json('nextCursor'));
        $response2->assertStatus(200);
        $response2->assertJsonCount(40, 'Resources');
        $response2->assertJson([
            'totalResults' => 100,
            'itemsPerPage' => 40
        ]);

        // assert the list of items in response 1 and 2 are different, compare the id of the user items
        $this->assertNotEquals(
            collect($response1->json('Resources'))->pluck('id')->toArray(),
            collect($response2->json('Resources'))->pluck('id')->toArray()
        );

        // assert the nextCursor is missing in response2
        $this->assertNull($response2->json('nextCursor'));
        $this->assertNotNull($response2->json('previousCursor'));
        $this->assertNull($response2->json('startIndex'));
    }

    public function testCursorPaginationFailure()
    {
        $response1 = $this->get('/scim/v2/Users?count=60&cursor=invalid');

        $response1->assertStatus(400);
        $response1->assertJson([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'status' => '400',
            'scimType' => 'invalidCursor'
        ]);

    }

    public function testCursorPaginationFailureMaxCount()
    {
        $response1 = $this->get('/scim/v2/Users?count=200&cursor');

        $response1->assertStatus(400);
        $response1->assertJson([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'status' => '400',
            'scimType' => 'invalidCount'
        ]);

    }

    public function testPagination()
    {
        $response = $this->get('/scim/v2/Users?startIndex=21&count=20');

        $response->assertStatus(200);
        $response->assertJsonCount(20, 'Resources');
        $response->assertJson([
            'totalResults' => 100,
            'itemsPerPage' => 20,
            'startIndex' => 21
        ]);

        $this->assertNull($response->json('nextCursor'));
        $this->assertNull($response->json('previousCursor'));
    }

    public function testSort()
    {
        $response = $this->get('/scim/v2/Users?sortBy=name.formatted');

        $response->assertStatus(200);

        $formattedNames = collect($response->json('Resources') ?? [])
            ->map(function ($resource) {
                return $resource['urn:ietf:params:scim:schemas:core:2.0:User']['name']['formatted'] ?? null;
            })
            ->filter() // Remove null values
            ->values() // Re-index the array
            ->toArray();

        $this->assertEquals(Arr::sort($formattedNames), $formattedNames);
    }

    public function testFilter()
    {
        // First get a username to search for
        $response = $this->get('/scim/v2/Users?startIndex=30&count=1');
        $userName = $response->json('Resources')[0]['urn:ietf:params:scim:schemas:core:2.0:User']['userName'];

        // Now search for this username
        $response = $this->get('/scim/v2/Users?filter=userName eq "'.$userName.'"');
        $response->assertStatus(200);

        $this->assertEquals(1, count($response->json('Resources')));
    }

    public function testFilterByGroup()
    {
        // Find a group
        $response = $this->get('/scim/v2/Groups?startIndex=30&count=1');
        $groupValue = $response->json('Resources')[0]['id'];

        // Now search for this username
        $response = $this->get('/scim/v2/Users?startIndex=30&count=1');
        $userValue = $response->json('Resources')[0]['id'];

        // SCIM Patch request
        $response = $this->patch('/scim/v2/Groups/' . $groupValue, [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [[
                "op" => "add",
                "path" => "members",
                "value" => [
                    [
                        "value" => $userValue
                    ]
                ]
            ]]
        ]);

        $response->assertStatus(200);

        $this->assertTrue(Group::find($groupValue)->members->pluck('id')->contains($userValue), 'User was not added to the group');        

        // SCIM Patch remove member request
        $response = $this->patch('/scim/v2/Groups/' . $groupValue, [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [[
                "op" => "remove",
                "path" => sprintf("members[value eq \"%s\"]", $userValue),
            ]]
        ]);

        $response->assertStatus(200);

        $this->assertFalse(Group::find($groupValue)->members->pluck('id')->contains($userValue), 'User was not removed from the group');
    }

    public function testSearch()
    {
        // First get a username to search for
        $response = $this->postJson('/scim/v2/Users/.search', [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:SearchRequest'],
            "startIndex" => 30,
            "count" => 1
        ]);

        $userName = $response->json('Resources')[0]['urn:ietf:params:scim:schemas:core:2.0:User']['userName'];

        // Now search for this username
        $response = $this->get('/scim/v2/Users?filter=userName eq "'.$userName.'"');
        $response->assertStatus(200);

        $this->assertEquals(1, count($response->json('Resources')));
    }

    public function testGroupAssignment()
    {
        // First get a username to search for
        $response = $this->get('/scim/v2/Users?startIndex=20&count=1');
        $groupValue = $response->json('Resources')[0]['urn:ietf:params:scim:schemas:core:2.0:User']['groups'][0]['value'];

        // find user id

        // (3) assign user to group via group endpoitn

        $this->assertTrue(count($response->json('Resources')) >= 1);
    }

    public function testPut()
    {
        $response = $this->putJson('/scim/v2/Users/1', [
            "id" => "1",
            "meta" => [
                "resourceType" => "User",
                "created" => "2010-01-23T04:56:22Z",
                "lastModified" => "2011-05-13T04:42:34Z",
                "version" => "W\/\"3694e05e9dff594\""
            ],
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
                ],
                "groups" => [
                    [
                        "value" => "1"
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
                "path" => "emails",
                "value" => [
                    [
                      "value" => "something@example.com",
                      "type" => "work",
                      "primary" => true
                    ]
                ]
            ]]
        ], $this->headers);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('something@example.com', $json['urn:ietf:params:scim:schemas:core:2.0:User']['emails'][0]['value']);
    }

    public function testPatchMultiple()
    {
        $response = $this->patch('/scim/v2/Users/2', [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [[
                "op" => "add",
                "value" => [
                    "userName" => "johndoe9858",
                    "name.formatted" => "John",
                    "active" => false
                ]
            ]]
        ]);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('johndoe9858', $json['urn:ietf:params:scim:schemas:core:2.0:User']['userName']);
        $this->assertEquals('John', $json['urn:ietf:params:scim:schemas:core:2.0:User']['name']['formatted']);
        $this->assertFalse($json['urn:ietf:params:scim:schemas:core:2.0:User']['active']);
    }

    public function testPatchMultipleReplace()
    {
        $response = $this->patch('/scim/v2/Users/2', [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [[
                "op" => "replace",
                "value" => [
                    "userName" => "johndoe9858",
                    "name.formatted" => "John",
                    "active" => true
                ]
            ]]
        ]);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('johndoe9858', $json['urn:ietf:params:scim:schemas:core:2.0:User']['userName']);
        $this->assertEquals('John', $json['urn:ietf:params:scim:schemas:core:2.0:User']['name']['formatted']);
        $this->assertTrue($json['urn:ietf:params:scim:schemas:core:2.0:User']['active']);
    }

    public function testPatchUsername()
    {
        $response = $this->patch('/scim/v2/Users/4', [
            "schemas" => [
                "urn:ietf:params:scim:api:messages:2.0:PatchOp",
            ],
            "Operations" => [[
                "op" => "add",
                "path" => "userName",
                "value" => "johndoe@example.com"
            ]]
        ]);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('urn:ietf:params:scim:schemas:core:2.0:User', $json);
        $this->assertEquals('johndoe@example.com', $json['urn:ietf:params:scim:schemas:core:2.0:User']['userName']);
    }

    public function testDelete()
    {
        $response = $this->deleteJson('/scim/v2/Users/1', [], $this->headers);
        $response->assertStatus(204);
    }

    public function testPost()
    {
        $response = $this->postJson('/scim/v2/Users', [
            // "id" => 1,
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
        $this->assertArrayNotHasKey('password', $json['urn:ietf:params:scim:schemas:core:2.0:User']);
    }

    public function testPostTopLevel()
    {
        $response = $this->post('/scim/v2/Users', [
            // "id" => 1,
            "schemas" => [
                "urn:ietf:params:scim:schemas:core:2.0:User",
            ],

            "userName" => "Dr. Marie Jo",
            "password" => "Password123",
            "emails" => [
                [
                    "value" => "mariejo@example.com",
                    "type" => "primary",
                    "primary" => true
                ]
            ]

        ]);

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

    public function testTotalResultsOnly(){
        $response = $this->get('/scim/v2/Users?count=0');
        $this->assertTrue(true);
    }
}
