<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Collection;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Complex;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Constant;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Eloquent;
use ArieTimmerman\Laravel\SCIMServer\Attribute\JSONCollection;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Meta;
use ArieTimmerman\Laravel\SCIMServer\Attribute\MutableCollection;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Schema as AttributeSchema;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\Group;
use Illuminate\Database\Eloquent\Model;

function a($name = null): Attribute
{
    return new Attribute($name);
}

function complex($name = null): Complex
{
    return new Complex($name);
}

function eloquent($name, $attribute = null): Attribute
{
    return new Eloquent($name, $attribute);
}


class SCIMConfig
{
    public function __construct()
    {
    }

    public function getConfigForResource($name)
    {
        $result = $this->getConfig();
        return @$result[$name];
    }

    public function getUserConfig()
    {
        return [

            // Set to 'null' to make use of auth.providers.users.model (App\User::class)
            'class' => Helper::getAuthUserClass(),
            'singular' => 'User',

            //eager loading
            'withRelations' => [],
            'description' => 'User Account',

            'map' => complex()->withSubAttributes(
                new class ('schemas', [
                    "urn:ietf:params:scim:schemas:core:2.0:User",
                ]) extends Constant {
                    public function replace($value, &$object, $path = null)
                    {
                        // do nothing
                        $this->dirty = true;
                    }
                },
                (new class ('id', null) extends Constant {
                    protected function doRead(&$object, $attributes = [])
                    {
                        return (string)$object->id;
                    }
                }
                ),
                new Meta('Users'),
                new AttributeSchema(Schema::SCHEMA_USER, true)->withSubAttributes(
                    eloquent('userName', 'name')->ensure('required'),
                    eloquent('active')->ensure('boolean')->default(false),
                    complex('name')->withSubAttributes(eloquent('formatted')),
                    eloquent('password')->ensure('nullable')->setReturned('never'),
                    (new class ('emails') extends Complex {
                        protected function doRead(&$object, $attributes = [])
                        {
                            return collect([$object->email])->map(fn($email) => [
                                'value' => $email,
                                'type' => 'other',
                                'primary' => true
                            ])->toArray();
                        }
                        public function add($value, Model &$object)
                        {
                            $object->email = $value[0]['value'];
                        }
                        public function replace($value, Model &$object, $path = null, $removeIfNotSet = false)
                        {
                            $object->email = $value[0]['value'];
                        }
                    })->withSubAttributes(
                        eloquent('value', 'email')->ensure('required', 'email'),
                        new Constant('type', 'other'),
                        new Constant('primary', true)
                    )->ensure('required', 'array')
                    ->setMultiValued(true),
                    new Collection('groups')->withSubAttributes(
                        eloquent('value', 'id'),
                        (new class ('$ref') extends Eloquent {
                            protected function doRead(&$object, $attributes = [])
                            {
                                return route(
                                    'scim.resource',
                                    [
                                    'resourceType' => 'Group',
                                    'resourceObject' => $object->id ?? "not-saved"
                                    ]
                                );
                            }
                        }),
                        eloquent('display', 'name')
                    ),
                    new JSONCollection('roles')->withSubAttributes(
                        eloquent('value')->ensure('required', 'min:3', 'alpha_dash:ascii'),
                        eloquent('display')->ensure('nullable', 'min:3', 'alpha_dash:ascii'),
                        eloquent('type')->ensure('nullable', 'min:3', 'alpha_dash:ascii'),
                        eloquent('primary')->ensure('boolean')->default(false)
                    )->ensure('nullable', 'array', 'max:20')
                ),
                new AttributeSchema('urn:ietf:params:scim:schemas:extension:enterprise:2.0:User', false)->withSubAttributes(
                    eloquent('employeeNumber')->ensure('nullable')
                )
            ),
        ];
    }

    public function getGroupConfig()
    {
        return [

            'class' => Group::class,
            'singular' => 'Group',

            //eager loading
            'withRelations' => [],
            'description' => 'Group',

            'map' => complex()->withSubAttributes(
                new class ('schemas', [
                    "urn:ietf:params:scim:schemas:core:2.0:Group",
                ]) extends Constant {
                    public function replace($value, &$object, $path = null)
                    {
                        // do nothing
                        $this->dirty = true;
                    }
                },
                (new class ('id', null) extends Constant {
                    protected function doRead(&$object, $attributes = [])
                    {
                        return (string)$object->id;
                    }
                }
                ),
                new Meta('Groups'),
                new AttributeSchema(Schema::SCHEMA_GROUP, true)->withSubAttributes(
                    eloquent('displayName')->ensure('required', 'min:3', function ($attribute, $value, $fail) {
                        // check if group does not exist or if it exists, it is the same group
                        $group = Group::where('displayName', $value)->first();
                        if ($group && (request()->route('resourceObject') == null || $group->id != request()->route('resourceObject')->id)) {
                            $fail('The name has already been taken.');
                        }
                    }),
                    new MutableCollection('members')->withSubAttributes(
                        eloquent('value', 'id')->ensure('required'),
                        (new class ('$ref') extends Eloquent {
                            protected function doRead(&$object, $attributes = [])
                            {
                                return route(
                                    'scim.resource',
                                    [
                                    'resourceType' => 'Users',
                                    'resourceObject' => $object->id ?? "not-saved"
                                    ]
                                );
                            }
                        }),
                        eloquent('display', 'name')
                    )->ensure('nullable', 'array')
                )
            ),
        ];
    }

    public function getConfig()
    {
        return [
            'Users' => $this->getUserConfig(),
            'Groups' => $this->getGroupConfig(),
        ];
    }
}
