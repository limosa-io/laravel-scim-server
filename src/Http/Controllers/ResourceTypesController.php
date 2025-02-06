<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse;
use ArieTimmerman\Laravel\SCIMServer\SCIM\ResourceType;

use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;

class ResourceTypesController extends Controller
{
    private $resourceTypes = null;
    
    public function __construct()
    {
        $config = resolve(SCIMConfig::class)->getConfig();
        
        $resourceTypes = [];
        
        foreach ($config as $key => $value) {
            $schemas = $value['map']->getSchemaNodes();

            $resourceTypes[] = new ResourceType(
                $value['singular'],
                $key,
                $key,
                $value['description'] ?? null,
                $schemas[0]->getName(),
                collect(array_slice($schemas, 1))->map(
                    function ($element) {
                        return [
                            'schema' => $element->getName(),
                            'required' => $element->required
                        ];
                    }
                )->toArray()
            );
        }
        
        $this->resourceTypes = collect($resourceTypes);
    }
    
    public function index()
    {
        return new ListResponse($this->resourceTypes, 1, $this->resourceTypes->count());
    }
    
    public function show(Request $request, $id = null)
    {
        $result = $this->resourceTypes->first(
            function ($value, $key) use ($id) {
                return $value->id == $id;
            }
        );
        
        if ($result == null) {
            throw (new SCIMException("Resource not found"))->setCode(404);
        }
        
        return $result;
    }
}
