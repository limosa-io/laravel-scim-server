<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

class ServiceProviderController extends Controller{

    public function index(){
        
        // TODO: The SCIM 2.0 specs provide very limited functionality in showing the capabilities of a server.
        
        return [
            "schemas" => ["urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig"],
            "documentationUri" => "http://example.com/help/scim.html",
            "patch" => [
                "supported" => false,
            ],
            "bulk" => [
                "supported" => false,
            ],
            "filter" => [
                "supported" => true,
            ],
            "changePassword" => [
                "supported" => false,
            ],
            "sort" => [
                "supported" => true,
            ],
            "etag" => [
                "supported" => false,
            ],
            "authenticationSchemes" => [
                [
                    "name" => "OAuth Bearer Token",
                    "description" =>
                    "Authentication scheme using the OAuth Bearer Token Standard",
                    "specUri" => "http://www.rfc-editor.org/info/rfc6750",
                    "documentationUri" => "http://example.com/help/oauth.html",
                    "type" => "oauthbearertoken",
                    "primary" => true,
                ],
                [
                    "name" => "HTTP Basic",
                    "description" =>
                    "Authentication scheme using the HTTP Basic Standard",
                    "specUri" => "http://www.rfc-editor.org/info/rfc2617",
                    "documentationUri" => "http://example.com/help/httpBasic.html",
                    "type" => "httpbasic",
                ],
            ],
            "meta" => [
                "location" => route('scim.serviceproviderconfig'),
                "resourceType" => "ServiceProviderConfig",
            		
            	//TODO: Format time stamps
                "created" => filectime(__FILE__),
                "lastModified" => filemtime(__FILE__),
                "version" => "W\/\"3694e05e9dff594\"",
            ],
        ];

    }

}