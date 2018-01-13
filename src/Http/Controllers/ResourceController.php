<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse;
use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Tmilos\ScimFilterParser\Parser;
use Tmilos\ScimFilterParser\Mode;
use ArieTimmerman\Laravel\SCIMServer\AttributeMapping;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;

class ResourceController extends Controller{
    
    /**
     * Create a new scim resource
     * @param Request $request
     * @param ResourceType $resourceType
     * @throws SCIMException
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function create(Request $request, ResourceType $resourceType){
    	
    	$class = $resourceType->getClass();
    	
    	$input = $request->input();
    	
    	//FIXME: Remove the need for unseeting schema. Deal with multivalued (non-complex) attributes, respecting ignore write. 
    	//Idea: Fix self::flatten, do not flat no associative array values 
    	//or better: do not flatten at ALL!
    	//unset($input['schemas']);
    	
    	$flattened = $input; //Helper::flatten($input);
    	
    	$resourceObject = new $class();
    	
    	foreach(array_keys($flattened) as $scimAttribute){
    	    
    		$attributeConfig = Helper::getAttributeConfig($resourceType, $scimAttribute);
    		
    		if($attributeConfig == null){
    			throw (new SCIMException(sprintf('Unknown attribute "%s"',$scimAttribute)))->setCode(400);
    		}else{
    			$attributeConfig->write($flattened[$scimAttribute],$resourceObject);
    		}
    		
    	}
        
    	//TODO: What if errors popup here
    	$resourceObject->save();
    	
    	return Helper::objectToSCIMResponse($resourceObject, $resourceType)->setStatusCode(201);
    	
    }

    public function show(Request $request, ResourceType $resourceType, $resourceObject){
    	
    	return Helper::objectToSCIMResponse($resourceObject, $resourceType);
    	
    }
    
    public function delete(Request $request, ResourceType $resourceType, $resourceObject){
                
        $resourceObject->delete();
        
        return response(null,204);
        
    }
    
    public function replace(Request $request, ResourceType $resourceType, $resourceObject){
        
        $input = $request->input();
        unset($input['schemas']);
        
        $flattened = Helper::flatten($input);
        
        $uses = [];
                 
        foreach(array_keys($flattened) as $scimAttribute){
        
            $attributeConfig = Helper::getAttributeConfig($resourceType, $scimAttribute);
            
            if($attributeConfig == null){
                throw new SCIMException("Unknown attribute \"" . $scimAttribute . "\".",400);
            }else{
                $attributeConfig->write($flattened[$scimAttribute],$resourceObject);
                
                $uses[] = $attributeConfig;
            }
        
        }
        
        $allAttributeConfigs = $resourceType->getAllAttributeConfigs();
                
        foreach($uses as $use){
            foreach($allAttributeConfigs as $key=>$option){
                if($use->id == $option->id){
                    unset($allAttributeConfigs[$key]);
                }
            }
        }
        
        foreach($allAttributeConfigs as $attributeConfig){
            // Do not write write-only attribtues (such as passwords)
            if($attributeConfig->isReadSupported() && $attributeConfig->isWriteSupported()){
                $attributeConfig->write(null,$resourceObject);
            }
        }
        
        $resourceObject->save();
        
        return Helper::objectToSCIMResponse($resourceObject, $resourceType);
        
    }
    
    public function update(Request $request, ResourceType $resourceType, $resourceObject){
    	    	
    	$input = $request->input();
    	
    	if($input['schemas'] !== ["urn:ietf:params:scim:api:messages:2.0:PatchOp"]){
    	    throw (new SCIMException(sprintf('Invalid schema "%s". MUST be "urn:ietf:params:scim:api:messages:2.0:PatchOp"',json_encode($input['schemas']))))->setCode(404);
    	}
    	
    	unset($input['schemas']);
    	
    	if(isset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'])){
    	    $input['Operations'] = $input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'];
    	    unset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations']);
    	}
    	
    	foreach( $input['Operations'] as $operation){
    	    
            switch(strtolower($operation['op'])){
                
                case "add":
                    
                    if(isset($operation['path'])){
                        
                        $attributeConfig = Helper::getAttributeConfig($resourceType, $operation['path']);
                        $attributeConfig->add($operation['value'], $resourceObject);
                        
                    }else{
                        foreach($operation['value'] as $key => $value){
                            $attributeConfig = Helper::getAttributeConfig($resourceType, $key);
                            $attributeConfig->add($value, $resourceObject);
                        }
                    }
                    
                    break;
                
                case "remove":
                                        
                    if(isset($operation['path'])){
                    
                        $attributeConfig = Helper::getAttributeConfig($resourceType, $operation['path']);
                        $attributeConfig->remove($resourceObject);
                    
                    }else{
                        throw new SCIMException('You MUST provide a "Path"');
                    }
                    
                    
                    break;
                    
                case "replace":
                    
                    if(isset($operation['path'])){
                        
                        $attributeConfig = Helper::getAttributeConfig($resourceType, $operation['path']);
                        $attributeConfig->replace($operation['value'], $resourceObject);
                        
                    }else{
                        foreach($operation['value'] as $key => $value){
                            $attributeConfig = Helper::getAttributeConfig($resourceType, $key);
                            $attributeConfig->replace($value, $resourceObject);
                        }
                    }
                    
                    break;
                    
                default:
                    throw new SCIMException(sprintf('Operation "%s" is not supported',$operation['op']));
                    
                 
                    
            }
            
            $resourceObject->save();
            
            return Helper::objectToSCIMResponse($resourceObject, $resourceType);
            
    	}
    	
    }
    
    public function index(Request $request, ResourceType $resourceType){
        
    	$class = $resourceType->getClass();
    	
    	// The 1-based index of the first query result. A value less than 1 SHALL be interpreted as 1.
    	$startIndex = max(1,intVal($request->input('startIndex',0)));
    	 
    	// Non-negative integer. Specifies the desired maximum number of query results per page, e.g., 10. A negative value SHALL be interpreted as "0". A value of "0" indicates that no resource results are to be returned except for "totalResults". 
    	$count = max(0,intVal($request->input('count',10)));
    	
    	$sortBy = null;
    	
    	if($request->input('sortBy')){
    		$sortBy = Helper::getEloquentSortAttribute($resourceType, $request->input('sortBy'));
    	}
    	    	
		$resourceObjectsBase = $class::when($filter = $request->input('filter'), function($query) use ($filter, $resourceType) {
			
			$parser = new Parser(Mode::FILTER());
			
			try {
				
				$node = $parser->parse($filter);
				
				Helper::scimFilterToLaravelQuery($resourceType, $query, $node);
				
			}catch(\Tmilos\ScimFilterParser\Error\FilterException $e){
				throw (new SCIMException($e->getMessage()))->setCode(400)->setScimType('invalidFilter');
			}
			
		} );
		
		$resourceObjects = $resourceObjectsBase->skip($startIndex - 1)->take($count);
		
		if($sortBy != null){
		  $resourceObjects = $resourceObjects->orderBy($sortBy, 'desc');
		}
		
		$resourceObjects = $resourceObjects->get();
		
		$totalResults = $resourceObjectsBase->count();
		$attributes = [];
		$excludedAttributes = [];
        
        return new ListResponse($resourceObjects, $startIndex, $totalResults, $attributes, $excludedAttributes, $resourceType);

    }

}