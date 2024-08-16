<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Tests\Model\Group;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GroupsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('users', function (Blueprint $table) {
            $table->string('employeeNumber')->nullable();
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
    }

    public function testGet()
    {
        config(['scim.omit_null_values' => false]);
        $response = $this->get('/scim/v2/Groups');
        $response->assertStatus(200);

        $response->assertJsonStructure([
            "schemas",
            "totalResults",
            "itemsPerPage",
            "startIndex",
            "Resources" => [
                '*' => [
                    'id',
                    'urn:ietf:params:scim:schemas:core:2.0:Group' => [
                        'members' => [
                            '*' => [
                                'value',
                                'display'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function testCreate(){
        $response = $this->post('/scim/v2/Groups', [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'], // Required
            'urn:ietf:params:scim:schemas:core:2.0:Group' => [
                'displayName' => 'TestGroup'
            ]
        ]);

        $response->assertJsonStructure([
            'id',
            'urn:ietf:params:scim:schemas:core:2.0:Group' => [
                'displayName'
            ]
        ]);

        $this->assertNotNull(Group::find($response->json('id')));
        $this->assertNotNull(Group::where('displayName', 'TestGroup')->first());

    }

    public function testBulk(){
        $response = $this->post('/scim/v2/Bulk', [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkRequest'], // Required
            'Operations' => [
                [
                    'method' => 'POST',
                    'path' => '/Groups',
                    'data' => [
                        'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'], // Required
                        'urn:ietf:params:scim:schemas:core:2.0:Group' => [
                            'displayName' => 'TestGroup'
                        ]
                    ]
                ],
                [
                    'method' => 'POST',
                    'path' => '/Groups',
                    'data' => [
                        'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'], // Required
                        'urn:ietf:params:scim:schemas:core:2.0:Group' => [
                            'displayName' => 'TestGroup2'
                        ]
                    ]
                ]
            ]
        ]);

        $response->assertJsonStructure([
            'schemas',
            'Operations' => [
                '*' => [
                    'method',
                    'location',
                    'status'
                ]
            ]
        ]);

        // confirm testgroup1 exists
        $this->assertNotNull(Group::where('displayName', 'TestGroup2')->first());
    }
}
