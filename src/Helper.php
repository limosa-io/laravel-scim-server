<?php
namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;
use Illuminate\Contracts\Support\Arrayable;
use Tmilos\ScimFilterParser\Ast\ComparisonExpression;
use Tmilos\ScimFilterParser\Ast\Negation;
use Tmilos\ScimFilterParser\Ast\Conjunction;
use Tmilos\ScimFilterParser\Ast\Disjunction;

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
        
        if (! empty($object) && isset($object[0]) && is_object($object[0])) {
            
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
         
        //var_dump($node);exit;
        
        if($node instanceof Negation){
            $filter = $node->getFilter();
    
            throw (new SCIMException('Negation filters not supported'))->setCode(400)->setScimType('invalidFilter');
             
        }else if($node instanceof ComparisonExpression){
             
            $operator = strtolower($node->operator);
    
            $attributeConfig = $resourceType->getMapping()->getSubNodeWithPath($node);
            
            $attributeConfig->applyWhereCondition($query, $operator, $node->compareValue);
             
        }else if($node instanceof Conjunction){
             
            
            foreach ($node->getFactors() as $factor){
                 
                $query->where(function($query) use ($factor,$resourceType){
                    Helper::scimFilterToLaravelQuery($resourceType, $query, $factor);
                });
                     
            }
             
        }else if($node instanceof Disjunction){
             
            foreach ($node->getTerms() as $term){
    
                $query->orWhere(function($query) use ($term, $resourceType){
                    Helper::scimFilterToLaravelQuery($resourceType, $query, $term);
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