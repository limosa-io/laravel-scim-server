<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;
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
            'name' => 'testgroup1',
            'displayName' => 'TestGroup'
        ]);

        $response->assertJsonStructure([
            'id',
            'urn:ietf:params:scim:schemas:core:2.0:Group' => [
                'displayName'
            ]
            
        ]);
    }
}
