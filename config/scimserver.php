<?php
use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping as A;
use ArieTimmerman\Laravel\SCIMServer\Attribute\ConstantAttributeMapping as C;

use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;

return [
    
    'Users' => [
        
        // Set to 'null' to make use of auth.providers.users.model (App\User::class)
        'class' => Helper::getAuthUserClass(),
        'singular' => 'User',
        'schema' => Schema::SCHEMA_USER,
        'map_unmapped' => true,
        'unmapped_namespace' => 'urn:ietf:params:scim:schemas:laravel:unmapped',
        'description' => 'User Account',
        
        // Map a SCIM attribute to an attribute of the object.
        'mapping' => [
            
            'id' => AttributeMapping::eloquent("id")->disableWrite(),
            
            'externalId' => null,
            
            'meta' => [
                'created' => AttributeMapping::eloquent("created_at")->disableWrite(),
                'lastModified' => AttributeMapping::eloquent("updated_at")->disableWrite(),
                
                'location' => (new AttributeMapping())->setRead(function ($object) {
                    return route('scim.resource', [
                        'name' => 'Users',
                        'id' => $object->id
                    ]);
                }),
                
                'resourceType' => AttributeMapping::constant("User")
            ],
            
            'schemas' => AttributeMapping::constant([
                'urn:ietf:params:scim:schemas:core:2.0:User',
                'example:name:space',
            ]),
            
            'example:name:space' => [
                'cityPrefix' => AttributeMapping::eloquent('cityPrefix')    
            ],
            
            'urn:ietf:params:scim:schemas:core:2.0:User' => [
                
                'userName' => AttributeMapping::eloquent("name"),
                
                'name' => [
                    'formatted' => AttributeMapping::eloquent("name"),
                    'familyName' => null,
                    'givenName' => null,
                    'middleName' => null,
                    'honorificPrefix' => null,
                    'honorificSuffix' => null
                ],
                
                'displayName' => null,
                'nickName' => null,
                'profileUrl' => null,
                'title' => null,
                'userType' => null,
                'preferredLanguage' => null, // Section 5.3.5 of [RFC7231]
                'locale' => null, // see RFC5646
                'timezone' => null, // see RFC6557
                'timezone' => null,
                'active' => null,
                
                'password' => AttributeMapping::eloquent('password')->disableRead(),
                
                // Multi-Valued Attributes
                'emails' => [[
                        "value" => AttributeMapping::eloquent("email"),
                        "display" => null,
                        "type" => AttributeMapping::constant("work"),
                        "primary" => AttributeMapping::constant(true)
                ]],
                
                'phoneNumbers' => [[
                    "value" => null,
                    "display" => null,
                    "type" => null,
                    "primary" => null
                ]],
                
                'ims' => [[
                    "value" => null,
                    "display" => null,
                    "type" => null,
                    "primary" => null
                ]], // Instant messaging addresses for the User
                
                'photos' => [[
                    "value" => null,
                    "display" => null,
                    "type" => null,
                    "primary" => null
                ]],
                
                'addresses' => [[
                    'formatted' => null,
                    'streetAddress' => null,
                    'locality' => null,
                    'region' => null,
                    'postalCode' => null,
                    'country' => null
                ]],
                
                'groups' => [[
                    'value' => null,
                    '$ref' => null,
                    'display' => null,
                    'type' => null,
                    'type' => null
                ]],
                
                'entitlements' => null,
                'roles' => null,
                'x509Certificates' => null
            ],
                        
        ]
    ]
]
;

			