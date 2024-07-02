<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Collection;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Complex;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Constant;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Eloquent;
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
                    }
                },
                (new class ('id') extends Attribute {
                    public function read(&$object)
                    {
                        return (string)$object->id;
                    }
                }
                )->disableWrite(),
                complex('meta')->withSubAttributes(
                    eloquent('created')->disableWrite(),
                    eloquent('lastModified')->disableWrite(),
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
                    })->disableWrite(),
                    new Constant('resourceType', 'User')
                ),
                complex(Schema::SCHEMA_USER, true)->withSubAttributes(
                    eloquent('userName', 'name')->ensure('required'),
                    complex('name')->withSubAttributes(eloquent('formatted', 'name')),
                    eloquent('password')->disableRead()->ensure('nullable'),
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
                    )->ensure('required', 'array')
                ),
                complex('urn:ietf:params:scim:schemas:extension:enterprise:2.0:User', true)->withSubAttributes(
                    eloquent('employeeNumber')->ensure('nullable')
                )
            ),
        ];
    }

    public function getConfig()
    {
        return [
            'Users' => $this->getUserConfig()
        ];
    }
}
