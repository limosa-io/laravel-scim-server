<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;

function a($name = null, $schemaNode = false): Attribute
{
    return new Attribute($name, $schemaNode);
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

            'map' => a()->object(
                a('schemas')->constant([
                    "urn:ietf:params:scim:schemas:core:2.0:User",
                ]),
                a('id')->setRead(
                    function (&$object) {
                        return (string)$object->id;
                    }
                )->disableWrite(),
                a('meta')->object(
                    a('created')->eloquent()->disableWrite(),
                    a('lastModified')->eloquent()->disableWrite(),
                    a('location')->setRead(
                        function ($object) {
                            return route(
                                'scim.resource',
                                [
                                'resourceType' => 'Users',
                                'resourceObject' => $object->id
                                ]
                            );
                        }
                    )->disableWrite(),
                    a('resourceType')->constant("User")
                ),
                a(Schema::SCHEMA_USER, true)->object(
                    a('userName')->eloquent('name')->ensure('required'),
                    a('name')->object(
                        a('formatted')->eloquent('name')
                    ),
                    a('password')->eloquent()->disableRead()->ensure('nullable'),
                    a('emails')->ensure('required', 'array')->collection(
                        a()->object(
                            a('value')->eloquent('email')->ensure('required', 'email'),
                            a('type')->constant('other')->ignoreWrite(),
                            a('primary')->constant(true)->ignoreWrite()
                        ),
                        a()->object(
                            a('value')->eloquent('email'),
                            a('type')->constant('work')->ignoreWrite(),
                            a('primary')->constant(true)->ignoreWrite()
                        )
                    )
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
