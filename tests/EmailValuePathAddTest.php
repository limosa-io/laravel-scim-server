<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Attribute\Complex;
use ArieTimmerman\Laravel\SCIMServer\Parser\Parser;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\User;
use Illuminate\Database\Eloquent\Model;

class EmailValuePathAddTest extends TestCase
{
    public function testAddOperationUpdatesEmailValue(): void
    {
        $user = User::query()->firstOrFail();

        $payload = [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
            'Operations' => [
                [
                    'op' => 'Add',
                    'path' => 'emails[type eq "other"].value',
                    'value' => 'someone@someplace.com',
                ],
            ],
        ];

        $response = $this->patchJson('/scim/v2/Users/' . $user->id, $payload);

        $response->assertStatus(200);

        $user->refresh();

        $this->assertSame('someone@someplace.com', $user->email);
    }

    public function testAddOperationCreatesNewElementWhenFilterMatchesNone(): void
    {
        $model = new class extends Model {
            protected $table = 'users';
            public $timestamps = false;
        };

        $model->emails = [
            ['value' => 'work@example.com', 'type' => 'work'],
        ];

        $attribute = $this->makeEmailAttribute();

        $path = Parser::parse('emails[type eq "other"].value');
        $path->shiftValuePathAttributes();

        $attribute->patch('add', 'someone@someplace.com', $model, $path);

        $this->assertCount(2, $model->emails);
        $this->assertSame('work@example.com', $model->emails[0]['value']);
        $this->assertSame('other', $model->emails[1]['type']);
        $this->assertSame('someone@someplace.com', $model->emails[1]['value']);
    }

    private function makeEmailAttribute(): Complex
    {
        return new class('emails') extends Complex {
            public function __construct($name)
            {
                parent::__construct($name);
                $this->setMultiValued(true);
            }

            protected function doRead(&$object, $attributes = [])
            {
                return $object->{$this->name} ?? [];
            }

            public function replace($value, Model &$object, Path $path = null, $removeIfNotSet = false)
            {
                $object->{$this->name} = $value;
                $this->dirty = true;
            }
        };
    }
}
