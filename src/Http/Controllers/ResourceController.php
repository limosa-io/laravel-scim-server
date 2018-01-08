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
    
    // TODO: What if keys are 0,1 etc
	public static function flatten($array, $prefix = '', $iteration = 1) {
		$result = array();
		
		foreach($array as $key=>$value) {
			
			if(is_array($value)) {
			    //TODO: Ugly code
				$result = $result + self::flatten($value, $prefix . $key . ($iteration == 1?':':'.'), 2);
			} else {
				$result[$prefix . $key] = $value;
			}
			
		}
		
		return $result;
	}
	
	
    
	/**
	 * 
	 * $scimAttribute could be
	 * - urn:ietf:params:scim:schemas:core:2.0:User.userName
	 * - userName
	 * - urn:ietf:params:scim:schemas:core:2.0:User.userName.name.formatted
	 * - urn:ietf:params:scim:schemas:core:2.0:User.emails.value
	 * - emails.value
	 * - emails.0.value
	 * - schemas.0 
	 * 
	 * TODO: Inject $name with ResourceType
	 *  
	 * @param unknown $name
	 * @param unknown $scimAttribute
	 * @return AttributeMapping
	 */
    public function getAttributeConfig(ResourceType $resourceType, $scimAttribute) {
    	
        $parser = new Parser(Mode::PATH());
        
        $scimAttribute = preg_replace('/\.[0-9]+$/', '', $scimAttribute);
        $scimAttribute = preg_replace('/\.[0-9]+\./', '.', $scimAttribute);
        
        $path = $parser->parse($scimAttribute);
        
        return $resourceType->getMapping()->getSubNodeWithPath($path);
    	
    }
    
    public function getEloquentSortAttribute(ResourceType $resourceType, $scimAttribute){
    	    	
    	$mapping = $this->getAttributeConfig($resourceType, $scimAttribute);
    	
    	if($mapping == null || $mapping->getSortAttribute() == null){
    		throw new \ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException("Invalid sort property",400);	
    	}
    	
    	return $mapping->getSortAttribute();
    	
    }
    
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
    	unset($input['schemas']);
    	
    	$flattened = self::flatten($input);
    	
    	$resourceObject = new $class();
    	
    	foreach(array_keys($flattened) as $scimAttribute){
    		
    		$attributeConfig = $this->getAttributeConfig($resourceType, $scimAttribute);
    		
    		if($attributeConfig == null){
    			throw new SCIMException("Unknown attribute \"" . $scimAttribute . "\".",400);
    		}else{
    			$attributeConfig->write($flattened[$scimAttribute],$resourceObject);
    		}
    		
    	}
    	
    	$resourceObject->save();
    	
    	return \response(Helper::objectToSCIMArray($resourceObject, $resourceType), 201);
    	
    }

    public function show(Request $request, ResourceType $resourceType, $id){
    	
    	$class = $resourceType->getClass();
    	
    	$resourceObject = $class::where("id",$id)->first();
    	
    	if($resourceObject == null){
    		throw new SCIMException("Resource " . $id . " not found",404);
    	}
    	
    	return Helper::objectToSCIMArray($resourceObject, $resourceType);
    	
    }
    
    public function replace(Request $request, ResourceType $resourceType, $id){
        
        $class = $resourceType->getClass();
         
        $resourceObject = $class::where("id",$id)->first();
        
        $input = $request->input();
        unset($input['schemas']);
        
        $flattened = self::flatten($input);
        
        $uses = [];
                 
        foreach(array_keys($flattened) as $scimAttribute){
        
            $attributeConfig = $this->getAttributeConfig($resourceType, $scimAttribute);
            
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
        
        return Helper::objectToSCIMArray($resourceObject, $resourceType);
        
    }
    
    //TODO: Auto inject $resourceObject
    public function update(Request $request, ResourceType $resourceType, $id){
    	
    	$class = $resourceType->getClass();
    	
    	$resourceObject = $class::where("id",$id)->first();
    	
    	$input = $request->input();
    	
    	if($input['schemas'] !== ["urn:ietf:params:scim:api:messages:2.0:PatchOp"]){
    	    throw new SCIMException('invalid schema');
    	}
    	
    	unset($input['schemas']);
    	
    	// "path":"name.familyName"
    	// "path":"addresses[type eq \"work\"]",
    	// members[value eq \"2819c223-7f76-453a-919d-413861904646\"]"
    	
    	//TODO: Also support urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations
    	foreach( $input['Operations'] as $operation){
    	    
    	    
    	        	    
            switch(strtolower($operation['op'])){
                
                case "add":
                    
                    if(isset($operation['path'])){
                        
                        $attributeConfig = $this->getAttributeConfig($resourceType, $operation['path']);
                        $attributeConfig->add($operation['value'], $resourceObject);
                        
                    }else{
                        foreach($operation['value'] as $key => $value){
                            $attributeConfig = $this->getAttributeConfig($resourceType, $key);
                            $attributeConfig->add($value, $resourceObject);
                        }
                    }
                    
                    break;
                
                case "remove":
                    
                    // TODO: here is path required
                    
                    foreach($operations['value'] as $key => $value){
                        $attributeConfig->remove($value, $resourceObject);
                    }
                    
                    break;
                    
                case "replace":
                
                    if(isset($operation['path'])){
                        
                        $attributeConfig = $this->getAttributeConfig($resourceType, $operation['path']);
                        $attributeConfig->replace($operation['value'], $resourceObject);
                        
                    }else{
                        foreach($operation['value'] as $key => $value){
                            $attributeConfig = $this->getAttributeConfig($resourceType, $key);
                            $attributeConfig->replace($value, $resourceObject);
                        }
                    }
                    
                    break;
                    
                default:
                    throw new SCIMException("not supported");
                 
                    
            }
            
            $resourceObject->save();
            
            return Helper::objectToSCIMArray($resourceObject, $resourceType);
            
    	}
    	
    	/*
    	    {
		     "schemas":
		       ["urn:ietf:params:scim:api:messages:2.0:PatchOp"],
		     "Operations":[{
		       "op":"add", // remove, replace
		       "value":{
		         "emails":[
		           {
		             "value":"babs@jensen.org",
		             "type":"home"
		           }
		         ],
		         "nickname":"Babs"
		     }]
		   }
		       
    	 */
    	
    	//$class->test = "asdg";
    	
    }
    
    public function index(Request $request, ResourceType $resourceType){
        
    	$class = $resourceType->getClass();
    	
    	// The 1-based index of the first query result. A value less than 1 SHALL be interpreted as 1.
    	$startIndex = max(1,intVal($request->input('startIndex',0)));
    	 
    	// Non-negative integer. Specifies the desired maximum number of query results per page, e.g., 10. A negative value SHALL be interpreted as "0". A value of "0" indicates that no resource results are to be returned except for "totalResults". 
    	$count = max(0,intVal($request->input('count',10)));
    	
    	$sortBy = "id";
    	
    	if($request->input('sortBy')){
    		$sortBy = $this->getEloquentSortAttribute($resourceType, $request->input('sortBy'));
    	}
    	
    	//var_dump((new $class())->getTable());exit;
    	// ::from( 'items as items_alias' )
    	
		$resourceObjectsBase = $class::when($filter = $request->input('filter'), function($query) use ($filter, $resourceType) {
			
			$parser = new Parser(Mode::FILTER());
			
			try {
				
				$node = $parser->parse($filter);
				
				Helper::scimFilterToLaravelQuery($resourceType, $query, $node);
				
			}catch(\Tmilos\ScimFilterParser\Error\FilterException $e){
				throw new SCIMException($e->getMessage(),400,"invalidFilter");
			}
			
		} );
		
		$resourceObjects = $resourceObjectsBase->skip($startIndex - 1)->take($count)->orderBy($sortBy, 'desc')->get();
		
		$totalResults = $resourceObjectsBase->count();
		$attributes = [];
		$excludedAttributes = [];
        
        return new ListResponse($resourceObjects, $startIndex, $totalResults, $attributes, $excludedAttributes, $resourceType);

    }

}