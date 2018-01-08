<?php
namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;
use Illuminate\Contracts\Support\Arrayable;

class Helper
{

    // var_dump(class_uses(config('auth.providers.users.model')));exit;
    public static function getAuthUserClass()
    {
        return config('auth.providers.users.model');
    }

    /**
     *
     * @param unknown $object            
     */
    public static function prepareReturn(Arrayable $object, ResourceType $resourceType = null)
    {
        $result = null;
        
        if (! empty($object) && is_object($object[0])) {
            
            if (! in_array('ArieTimmerman\Laravel\SCIMServer\Traits\SCIMResource', class_uses(get_class($object[0])))) {
                
                $result = [];
                
                foreach ($object as $key => $value) {
                    $result[] = self::objectToSCIMArray($value, $resourceType);
                }
            }
        }
        
        if ($result == null) {
            $result = $object;
        }
        
        return $result;
    }
    
    // TODO: Auto map eloquent attributes with scim naming to the correct attributes
    public static function objectToSCIMArray($object, ResourceType $resourceType = null)
    {
        $userArray = null;
        
        if (method_exists($object, "toArray_fromParent")) {
            $userArray = $object->toArray_fromParent();
        } else {
            
            $userArray = $object->toArray();
            
            if (method_exists($object, 'getDates')) {
                
                $dateAttributes = $object->getDates();
                foreach ($dateAttributes as $dateAttribute) {
                    if (isset($userArray[$dateAttribute])) {
                        $userArray[$dateAttribute] = $object->getAttribute($dateAttribute)->format('c');
                    }
                }
            }
            
        }
        
        $result = [];
        
        if ($resourceType != null) {
            
            $mapping = $resourceType->getMapping();
            
            $uses = $mapping->getEloquentAttributes();
            
            $result = $mapping->read($object);
                        
            foreach ($uses as $key) {
                unset($userArray[$key]);
            }
                        
            if (! empty($userArray) && $resourceType->getConfiguration()['map_unmapped']) {
                
                $namespace = $resourceType->getConfiguration()['unmapped_namespace'];
                
                $result[$namespace] = [];
                
                foreach ($userArray as $key => $value) {
                    $result[$namespace][$key] = AttributeMapping::eloquentAttributeToString($value);
                }
            }
            
        }else{
            $result = $userArray;
        }
        
        return $result;
    }

    /**
     * See https://tools.ietf.org/html/rfc7644#section-3.4.2.2
     * @param unknown $query
     * @param unknown $node
     * @throws SCIMException
     */
    public static function scimFilterToLaravelQuery(ResourceType $resourceType, &$query, $node){
         
        if($node instanceof Negation){
            $filter = $node->getFilter();
    
            throw new SCIMException("Negation filters not supported",400,"invalidFilter");
             
        }else if($node instanceof ComparisonExpression){
             
            $operator = strtolower($node->operator);
    
            $attributeConfig = $this->getAttributeConfig($resourceType, $node->attributePath->schema ? $node->attributePath->schema . ':' . implode('.', $node->attributePath->attributeNames) : implode('.', $node->attributePath->attributeNames));
    
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
                    if(method_exists($query, 'whereNotNull')){
                        $query->whereNotNull($attributeConfig->eloquentAttribute);
                    }else{
                        $query->where($attributeConfig->eloquentAttribute,'!=',null);
                    }
    
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
                    $this->scimFilterToLaravelQuery($resourceType, $query, $factor);
                });
                     
            }
             
        }else if($node instanceof Disjunction){
             
            foreach ($node->getTerms() as $term){
    
                $query->orWhere(function($query) use ($term){
                    $this->scimFilterToLaravelQuery($resourceType, $query, $term);
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
    
}