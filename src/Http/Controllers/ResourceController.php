<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse;
use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Tmilos\ScimFilterParser\Parser;
use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Ast\Negation;
use Tmilos\ScimFilterParser\Ast\ComparisonExpression;
use Tmilos\ScimFilterParser\Ast\Conjunction;
use Tmilos\ScimFilterParser\Ast\Disjunction;
use Tmilos\ScimFilterParser\Ast\Factor;
use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use ArieTimmerman\Laravel\SCIMServer\AttributeMapping;
use Tmilos\ScimFilterParser\Ast\ValuePath;
use Illuminate\Support\Facades\DB;

class ResourceController extends Controller{

	public static function flatten($array, $prefix = '') {
		$result = array();
		
		foreach($array as $key=>$value) {
			
			if(is_array($value)) {
				$result = $result + self::flatten($value, $prefix . $key . '.');
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
	 * @param unknown $name
	 * @param unknown $scimAttribute
	 * @return AttributeMapping
	 */
    public function getAttributeConfig($name, $scimAttribute) {
    	
    	$mapping = config("scimserver")[$name]['mapping'];
    	
    	$schema = null;
    	
    	if(preg_match('/^(.*):/', $scimAttribute,$matches)){
    	    $schema = $matches[1];
    	}
    	
    	preg_match('/^.*?:?([^:]*?)$/', $scimAttribute,$matches);
    	
    	$scimAttribute = $matches[1];
    	
    	$scimAttribute = preg_replace('/\.[0-9]+$/', '', $scimAttribute);
    	$scimAttribute = preg_replace('/\.[0-9]+\./', '.', $scimAttribute);
    	
    	$attributeParts = explode(".", $scimAttribute);
    	
    	if($schema == null && !in_array($attributeParts[0], Schema::ATTRIBUTES_CORE)){
    	    $schema = $mapping['schemas']->eloquentAttribute[0];
    	}
    	
    	$elements = [];
    	
    	if($schema != null){
    	    $elements[] = $schema;
    	}
    	
    	array_push($elements,...explode(".", $scimAttribute));
    	
    	//TODO: This is not nice. Most likely incorrect.
    	if(config("scimserver")[$name]['map_unmapped'] && $schema == config("scimserver")[$name]['unmapped_namespace']){
    	    
    	   $mapping = new \ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping($scimAttribute);
    	   
    	}else{   	 
        	foreach ($elements as $value) {
        	    
        	    // Do something with ->getMapping();
        	    
        	    if(is_array($mapping)){
        	        $mapping = @$mapping[$value];
        	    }else if($mapping instanceof \ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping){
        	       $mapping = $mapping->getMapping($value);   
        	    }else{
        	       throw new SCIMException("Unknown attribute: " . implode(":", $elements));
        	    }
        		
        	}
    	}
    	
    	return $mapping;
    	
    }
    
    public function getEloquentSortAttribute($name, $scimAttribute){
    	    	
    	$mapping = $this->getAttributeConfig($name, $scimAttribute);
    	
    	if($mapping == null || $mapping->getSortAttribute() == null){
    		throw new \ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException("Invalid sort property",400);	
    	}
    	
    	return $mapping->getSortAttribute();
    	
    }
    
    public function create(Request $request, $name){
    	
    	$class = @config("scimserver")[$name]['class'];
    	 
    	if($class == null){
    		throw new SCIMException("Not found",404);
    	}
    	
    	$input = $request->input();
    	unset($input['schemas']);
    	
    	$flattened = self::flatten($input);
    	
    	$resourceObject = new $class();
    	
    	foreach(array_keys($flattened) as $scimAttribute){
    		
    		$attributeConfig = $this->getAttributeConfig($name, $scimAttribute);
    		
    		if($attributeConfig == null){
    			throw new SCIMException("Unknown attribute \"" . $scimAttribute . "\".",400);
    		}else{
    			$attributeConfig->write($request->input($scimAttribute),$resourceObject);
    		}
    		
    	}
    	
    	$resourceObject->save();
    	
    	return \response($resourceObject, 201);
    	
    }

    public function show(Request $request, $name, $id){
    	
    	$class = @config("scimserver")[$name]['class'];
    	
    	if($class == null){
    		throw new SCIMException("Not found",404);
    	}
    	
    	$resourceObject = $class::where("id",$id)->first();
    	
    	if($resourceObject == null){
    		throw new SCIMException("Resource " . $id . " not found",404);
    	}
    	
    	return $resourceObject;
    	
    }
    
    public function update(Request $request, $name=null){
    	
    	$class = @config("scimserver")[$name]['class'];
    	 
    	if($class == null){
    		throw new SCIMException("Not found",404);
    	}
    	
    	$input = $request->input();
    	unset($input['schemas']);
    	 
    	//$flattened = self::flatten($input);
    	
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
    
    /**
     * See https://tools.ietf.org/html/rfc7644#section-3.4.2.2
     * @param unknown $query
     * @param unknown $node
     * @throws SCIMException
     */
    protected function scimFilterToLaravelQuery($name, &$query, $node){
    	
    	if($node instanceof Negation){
    		$filter = $node->getFilter();
    		
    		throw new SCIMException("Negation filters not supported",400,"invalidFilter");
    		 
    	}else if($node instanceof ComparisonExpression){
    		 
    		$operator = strtolower($node->operator);
    		    		
    		$attributeConfig = $this->getAttributeConfig($name, $node->attributePath->schema ? $node->attributePath->schema . ':' . implode('.', $node->attributePath->attributeNames) : implode('.', $node->attributePath->attributeNames));
    		
    		// Consider calling something like $attributeConfig->doQuery($query,$attribute,$operation,$value)
    		// Consider calling something like $attributeConfig->doQuery($query,$subQuery)
    		
    		switch($operator){
    			
    			case "eq":
    				$query->where($attributeConfig->eloquentAttribute,$node->compareValue);
    				break;
    			case "ne":
    				$query->where($attributeConfig->eloquentAttribute,'<>',$node->compareValue);
	    			break;
	    		case "co":
	    			//TODO: escape % characters etc, require min length
	    			$query->where($attributeConfig->eloquentAttribute,'like','%' . addcslashes($node->compareValue, '%_') . '%');
	    			break;
    			case "sw":
    				//TODO: escape % characters etc, require min length
    				$query->where($attributeConfig->eloquentAttribute,'like',addcslashes($node->compareValue, '%_') . '%');
    				break;	
    			case "ew":
    				//TODO: escape % characters etc, require min length
    				$query->where($attributeConfig->eloquentAttribute,'like','%' . addcslashes($node->compareValue, '%_'));
    				break;    	
    			case "pr":
    				//TODO: Check for existence for complex attributes
    				$query->whereNotNull($attributeConfig->eloquentAttribute);
    				break;    
    			case "gt":
    				$query->where($attributeConfig->eloquentAttribute,'>',$node->compareValue);
    				break;
    			case "ge":
    				$query->where($attributeConfig->eloquentAttribute,'>=',$node->compareValue);
    				break;
    			case "lt":
    				$query->where($attributeConfig->eloquentAttribute,'<',$node->compareValue);
    				break;
    			case "le":
    				$query->where($attributeConfig->eloquentAttribute,'<=',$node->compareValue);
    				break;    				
    			default:
    				die("Not supported!!");
    				break;
    			
    		}
    		 
    	}else if($node instanceof Conjunction){
    		 
    		foreach ($node->getFactors() as $factor){
    			
    			$query->where(function($query) use ($factor){
    				$this->scimFilterToLaravelQuery($name, $query, $factor);
    			});
    	
    		}
    		 
    	}else if($node instanceof Disjunction){
    		 
    		foreach ($node->getTerms() as $term){
    			 
    			$query->orWhere(function($query) use ($term){
    				$this->scimFilterToLaravelQuery($name, $query, $term);
    			});
    				 
    		}
    	
    	}else if($node instanceof ValuePath){
    	    
    	    // ->filer
    	    $getAttributePath = function() {
    	        return $this->attributePath;
    	    };
    	    
    	    $getFilter = function() {
    	        return $this->filter;
    	    };
    	    
//     	    var_dump($getAttributePath->call($node));
//     	    var_dump($getFilter->call($node));
    	    
    	    // $mode->getTable()
    	    
    	    $query->whereExists(function($query){
    	        $query->select(DB::raw(1))
    	        ->from('users AS users2')
    	        ->whereRaw('users.id = users2.id');
    	    });
    	    
    	    
            //$node->
            
    	}else if($node instanceof Factor){
    	    var_dump($node);
    		die("Not ok hier!\n");
    	}
    	
    }
    
    public function index(Request $request, $name){
    	
    	$class = @config("scimserver")[$name]['class'];
    	
    	if($class == null){
    		throw new SCIMException("Not found",404);
    	}
    	
    	// The 1-based index of the first query result. A value less than 1 SHALL be interpreted as 1.
    	$startIndex = max(1,intVal($request->input('startIndex',0)));
    	 
    	// Non-negative integer. Specifies the desired maximum number of query results per page, e.g., 10. A negative value SHALL be interpreted as "0". A value of "0" indicates that no resource results are to be returned except for "totalResults". 
    	$count = max(0,intVal($request->input('count',10)));
    	
    	$sortBy = "id";
    	
    	if($request->input('sortBy')){
    		$sortBy = $this->getEloquentSortAttribute($name, $request->input('sortBy'));
    	}
    	
    	//var_dump((new $class())->getTable());exit;
    	// ::from( 'items as items_alias' )
    	
		$resourceObjectsBase = $class::when($filter = $request->input('filter'), function($query) use ($filter, $name) {
			
			$parser = new Parser(Mode::FILTER());
			
			try {
				
				$node = $parser->parse($filter);
				
				$this->scimFilterToLaravelQuery($name, $query, $node);
				
			}catch(\Tmilos\ScimFilterParser\Error\FilterException $e){
				throw new SCIMException($e->getMessage(),400,"invalidFilter");
			}
			
		} );
		
		$resourceObjects = $resourceObjectsBase->skip($startIndex - 1)->take($count)->orderBy($sortBy, 'desc')->get();
		
		$totalResults = $resourceObjectsBase->count();
		$attributes = [];
		$excludedAttributes = [];
        
        return new ListResponse($resourceObjects, $startIndex, $totalResults, $attributes, $excludedAttributes);

    }

}