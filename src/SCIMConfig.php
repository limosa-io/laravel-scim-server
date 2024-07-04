<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Collection;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Complex;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Constant;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Eloquent;
use ArieTimmerman\Laravel\SCIMServer\Attribute\MutableCollection;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\Group;
use Illuminate\Database\Eloquent\Model;

function a($name = null, $schemaNode = false): Attribute
{
    return new Attribute($name, $schemaNode);
}

function complex($name = null, $schemaNode = false): Complex
{
    return new Complex($name, $schemaNode);
}

function eloquent($name, $attribute = null, $schemaNode = false): Attribute
{
    return new Eloquent($name, $attribute, $schemaNode);
}


class SCIMConfig
{
    public function getConfigForResource($name)
    {
        if ($name == 'Users') {
            return $this->getUserConfig();
        } elseif ($name == 'Groups') {
            return $this->getGroupConfig();
        } else {
            $result = $this->getConfig();
            return @$result[$name];
        }
    }

    public function getUserConfig()
    {
        return [

            // Set to 'null' to make use of auth.providers.users.model (App\User::class)
            'class' => Helper::getAuthUserClass(),

            // Set to 'null' to make use of $class::query()
            'query' => null,

            // Set to 'null' to make use new $class()
            'factory' => null,

            'validations' => [
                'urn:ietf:params:scim:schemas:core:2\.0:User:userName' => 'required',
                'urn:ietf:params:scim:schemas:core:2\.0:User:password' => 'nullable',
                'urn:ietf:params:scim:schemas:core:2\.0:User:active' => 'boolean',
                'urn:ietf:params:scim:schemas:core:2\.0:User:emails' => 'required|array',
                'urn:ietf:params:scim:schemas:core:2\.0:User:emails.*.value' => 'required|email',
                'urn:ietf:params:scim:schemas:core:2\.0:User:roles' => 'nullable|array',
                'urn:ietf:params:scim:schemas:core:2\.0:User:roles.*.value' => 'required',
            ],

            'singular' => 'User',
            'schema' => [
                Schema::SCHEMA_USER,
                // 'example:name:space'
            ],

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
                    public function read(&$object)
                    {
                        return (string)$object->id;
                    }
                }
                ),
                complex('meta')->withSubAttributes(
                    eloquent('created'),
                    eloquent('lastModified'),
                    (new class ('location') extends Eloquent {
                        public function read(&$object)
                        {
                            return route(
                                'scim.resource',
                                [
                                'resourceType' => 'Users',
                                'resourceObject' => $object->id
                                ]
                            );
                        }
                    }),
                    new Constant('resourceType', 'User')
                ),
                complex(Schema::SCHEMA_USER, true)->withSubAttributes(
                    eloquent('userName', 'name')->ensure('required'),
                    complex('name')->withSubAttributes(eloquent('formatted', 'name')),
                    eloquent('password')->ensure('nullable'),
                    (new class ('emails') extends Complex {
                        public function read(&$object)
                        {
                            return collect([$object->email])->map(function ($email) {
                                return [
                                    'value' => $email,
                                    'type' => 'other',
                                    'primary' => true
                                ];
                            })->toArray();
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
                    )->ensure('required', 'array'),
                    (new Collection('groups'))->withSubAttributes(
                        eloquent('value', 'id'),
                        (new class ('$ref') extends Eloquent {
                            public function read(&$object)
                            {
                                return route(
                                    'scim.resource',
                                    [
                                    'resourceType' => 'Group',
                                    'resourceObject' => $object->id
                                    ]
                                );
                            }
                        }),
                        eloquent('display', 'name')
                    ),
                ),
                complex('urn:ietf:params:scim:schemas:extension:enterprise:2.0:User', true)->withSubAttributes(
                    eloquent('employeeNumber')->ensure('nullable')
                )
            ),
        ];
    }

    public function getGroupConfig()
    {
        return [

            'class' => Group::class,
            'validations' => [
               'urn:ietf:params:scim:schemas:core:2\.0:Group:displayName' => 'nullable',
                'urn:ietf:params:scim:schemas:core:2\.0:Group:name' => ['required','min:3',function ($attribute, $value, $fail) {
                    // check if group does not exist or if it exists, it is the same group
                    $group = Group::where('name', $value)->first();
                    if ($group && (request()->route('resourceObject') == null || $group->id != request()->route('resourceObject')->id)) {
                        $fail('The name has already been taken.');
                    }
                }],
                'urn:ietf:params:scim:schemas:core:2\.0:Group:members' => 'nullable|array',

                // Check for existing in the functions itself. More effecient due to 'whereIn' searches
                'urn:ietf:params:scim:schemas:core:2\.0:Group:members.*.value' => 'required',
            ],

            'singular' => 'Group',
            'schema' => [
                Schema::SCHEMA_GROUP,
                // 'example:name:space'
            ],

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
                    public function read(&$object)
                    {
                        return (string)$object->id;
                    }
                }
                ),
                complex('meta')->withSubAttributes(
                    eloquent('created'),
                    eloquent('lastModified'),
                    (new class ('location') extends Eloquent {
                        public function read(&$object)
                        {
                            return route(
                                'scim.resource',
                                [
                                'resourceType' => 'Groups',
                                'resourceObject' => $object->id
                                ]
                            );
                        }
                    }),
                    new Constant('resourceType', 'User')
                ),
                complex(Schema::SCHEMA_GROUP, true)->withSubAttributes(
                    eloquent('name')->ensure('required'),
                    eloquent('displayName')->ensure('required'),

                    (new MutableCollection('members'))->withSubAttributes(
                        eloquent('value', 'id'),
                        (new class ('$ref') extends Eloquent {
                            public function read(&$object)
                            {
                                return route(
                                    'scim.resource',
                                    [
                                    'resourceType' => 'Users',
                                    'resourceObject' => $object->id
                                    ]
                                );
                            }
                        }),
                        eloquent('display', 'name')
                    ),
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
