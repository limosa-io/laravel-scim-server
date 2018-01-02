<?php
use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping as A;
use ArieTimmerman\Laravel\SCIMServer\Attribute\ConstantAttributeMapping as C;
use ArieTimmerman\Laravel\SCIMServer\Attribute\ReadOnlyAttributeMapping as R;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Complex;

use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use ArieTimmerman\Laravel\SCIMServer\Attribute\WriteOnlyAttributeMapping;
use ArieTimmerman\Laravel\SCIMServer\Helper;

return [
    
    'Users' => [
        
        // Set to 'null' to make use of auth.providers.users.model (App\User::class)
        'class' => Helper::getAuthUserClass(),
        'singular' => 'User',
        'schema' => Schema::SCHEMA_USER,
        'description' => 'User Account',
        
        // Map a SCIM attribute to an attribute of the object.
        'mapping' => [
            
            'id' => new R("id"),
            
            'externalId' => null,
            
            'meta' => [
                'created' => new R("created_at", function ($object) {
                    return $object->created_at->format('c');
                }),
                'lastModified' => new R("updated_at", function ($object) {
                    return $object->updated_at->format('c');
                }),
                
                'location' => new R("name", function ($object) {
                    return route('scim.resource', [
                        'name' => 'Users',
                        'id' => $object->id
                    ]);
                }),
                
                'resourceType' => new C("User")
            ],
            
            "schemas" => new C([
                "urn:ietf:params:scim:schemas:core:2.0:User",
                "example:name:space",
            ]),
            
            'urn:ietf:params:scim:schemas:core:2.0:User' => [
                
                'userName' => new A("name"),
                
                'name' => [
                    'formatted' => new A("name"),
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
                
                'password' => new WriteOnlyAttributeMapping('password'),
                
                // Multi-Valued Attributes
                'emails' => new Complex('email',function($user){
                    return [[
                        "value" => new A("email"),
                        "display" => null,
                        "type" => new C("work"),
                        "primary" => true
                    ]];
                }),
                
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
            
            // TODO: Auto map all unmapped serialized attributes to a custom schema
            
        ]
    ]
]
;

			