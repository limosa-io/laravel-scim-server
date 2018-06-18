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
use Validator;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\Events\Delete;
use ArieTimmerman\Laravel\SCIMServer\Events\Get;
use ArieTimmerman\Laravel\SCIMServer\Events\Create;
use ArieTimmerman\Laravel\SCIMServer\Events\Replace;
use ArieTimmerman\Laravel\SCIMServer\Events\Patch;
use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;

class ResourceController extends Controller{
    

    protected static function replaceKeys(array $input) {

        $return = array();
        foreach ($input as $key => $value) {
            if (strpos($key, '_') > 0)
                $key = str_replace('___','.',$key);
    
            if (is_array($value))
                $value = self::replaceKeys($value); 
    
            $return[$key] = $value;
        }
        return $return;
    }

    protected function validateScim(ResourceType $resourceType, $flattened){

        $forValidation = [];
        $validations = $resourceType->getValidations();
        $simpleValidations = [];

        foreach($flattened as $key => $value){
            $forValidation[preg_replace('/([^*])\.([^*])/','${1}___${2}',$key)] = $value;
        }
        
        foreach($validations as $key => $value){
            $simpleValidations[preg_replace('/([^*])\.([^*])/','${1}___${2}',$key)] = $value;

        }
        
        $validator = Validator::make($forValidation, $simpleValidations);

        if ($validator->fails()) {

            // $errors = [];

            $e = $validator->errors();


            $e = self::replaceKeys($e->toArray());

            // foreach($e['messages']->all() as $key=>$value){
            //     $errors[$key] = str_replace('___',':',$key);
            // }

            // var_dump($errors);exit;

            throw (new SCIMException('Invalid data!'))->setCode(400)->setScimType('invalidSyntax')->setErrors( $e );
        }

        $valid = $validator->valid();
        
        foreach($valid as $key => $value){
            $flattened[str_replace(['___'],['.'],$key)] = $value;
        }

        return $flattened;


    }

    /**
     * Create a new scim resource
     * @param Request $request
     * @param ResourceType $resourceType
     * @throws SCIMException
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function create(Request $request, ResourceType $resourceType){
                
        $input = $request->input();

        $flattened = Helper::flatten($input, $input['schemas'] );
        $flattened = $this->validateScim($resourceType, $flattened);

        // foreach($valid as $key => $value){
        //     $flattened[str_replace(['2_0'],['2.0'],$key)] = $value;
        // }

        $class = $resourceType->getClass();
        
        /** @var Model */
    	$resourceObject = new $class();
    	
    	$allAttributeConfigs = [];
    	
    	foreach($flattened as $scimAttribute=>$value){
    	    
    		$attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $scimAttribute);
			$attributeConfig->add($value,$resourceObject);
			$allAttributeConfigs[] = $attributeConfig;
    		
    	}
    	
    	//TODO: What if errors popup here
        $resourceObject->save();
        
    	foreach($allAttributeConfigs as &$attributeConfig){
    	    $attributeConfig->writeAfter($flattened[$attributeConfig->getFullKey()],$resourceObject);
        }
        
        event(new Create($resourceObject));
    	
    	return Helper::objectToSCIMResponse($resourceObject, $resourceType)->setStatusCode(201);
    	
    }
    
    public function show(Request $request, ResourceType $resourceType, Model $resourceObject){
        
        event(new Get($resourceObject));

    	return Helper::objectToSCIMResponse($resourceObject, $resourceType);
    	
    }
    
    public function delete(Request $request, ResourceType $resourceType, Model $resourceObject){
                
        $resourceObject->delete();

        event(new Delete($resourceObject));
        
        return response(null,204);
        
    }
    
    public function replace(Request $request, ResourceType $resourceType, $resourceObject){
                
        // $schemas =  $request->input('schemas');
        // var_dump($schemas);exit;

        // $resourceType->getSchema()
        $flattened = Helper::flatten($request->input(),$resourceType->getSchema());
        $flattened = $this->validateScim($resourceType, $flattened);

        //Keep an array of written values
        $uses = [];
        
        //Write all values
        foreach($flattened as $scimAttribute=>$value){
        
            $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $scimAttribute);

            if($attributeConfig->isWriteSupported() ){
                $attributeConfig->replace($value,$resourceObject);
            }
            
            $uses[] = $attributeConfig;
        
        }
        
        //Find values that have not been written in order to empty these.
        $allAttributeConfigs = $resourceType->getAllAttributeConfigs();
                
        foreach($uses as $use){
            foreach($allAttributeConfigs as $key=>$option){
                if($use->getFullKey() == $option->getFullKey()){
                    unset($allAttributeConfigs[$key]);
                }
            }
        }
        
        foreach($allAttributeConfigs as $attributeConfig){
            // Do not write write-only attribtues (such as passwords)
            if($attributeConfig->isReadSupported() && $attributeConfig->isWriteSupported()){
            //   $attributeConfig->remove($resourceObject);
            }
        }

        $resourceObject->save();

        event(new Replace($resourceObject));
        
        return Helper::objectToSCIMResponse($resourceObject, $resourceType);
        
    }
    
    public function update(Request $request, ResourceType $resourceType, Model $resourceObject){
                
        //TODO: implement validations

        $input = $request->input();
    	
    	if($input['schemas'] !== ["urn:ietf:params:scim:api:messages:2.0:PatchOp"]){
    	    throw (new SCIMException(sprintf('Invalid schema "%s". MUST be "urn:ietf:params:scim:api:messages:2.0:PatchOp"',json_encode($input['schemas']))))->setCode(404);
    	}
    	    	
    	if(isset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'])){
    	    $input['Operations'] = $input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'];
    	    unset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations']);
        }
        
        $oldObject = Helper::objectToSCIMArray($resourceObject, $resourceType);
    	
    	foreach( $input['Operations'] as $operation){
    	    
            switch(strtolower($operation['op'])){
                
                case "add":
                    
                    if(isset($operation['path'])){
                        
                        $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $operation['path']);
                        $attributeConfig->add($operation['value'], $resourceObject);
                        
                    }else{
                        foreach($operation['value'] as $key => $value){
                            $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $key);
                            $attributeConfig->add($value, $resourceObject);
                        }
                    }
                    
                    break;
                
                case "remove":
                                        
                    if(isset($operation['path'])){
                    
                        $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $operation['path']);
                        $attributeConfig->remove($resourceObject);
                    
                    }else{
                        throw new SCIMException('You MUST provide a "Path"');
                    }
                    
                    
                    break;
                    
                case "replace":
                    
                    if(isset($operation['path'])){
                        
                        $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $operation['path']);
                        $attributeConfig->replace($operation['value'], $resourceObject);
                        
                    }else{
                        foreach($operation['value'] as $key => $value){
                            $attributeConfig = Helper::getAttributeConfigOrFail($resourceType, $key);
                            $attributeConfig->replace($value, $resourceObject);
                        }
                    }
                    
                    break;
                    
                default:
                    throw new SCIMException(sprintf('Operation "%s" is not supported',$operation['op']));
                    
            }

            $dirty = $resourceObject->getDirty();

            // TODO: prevent something from getten written before ...
            $newObject = Helper::flatten(Helper::objectToSCIMArray($resourceObject, $resourceType), $resourceType->getSchema());

            $this->validateScim($resourceType, $newObject);
            
            $resourceObject->save();

            event(new Patch($resourceObject));
            
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

        $resourceObjects = $resourceObjects->with($resourceType->getWithRelations());
		
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