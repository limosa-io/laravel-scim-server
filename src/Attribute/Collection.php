<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class Collection extends AttributeMapping {
    
    protected $collection = null;
    
    public function setStaticCollection($collection){
        $this->collection = $collection;
        
        return $this;
    }
    
    //TODO: Implement
    public function add($value, $object){
        // insert new mail if possible
    }
    
    //TODO: Implement
    public function remove($value, $object){
        // clean all matching the filter
    }
    
    //TODO: Implement
    public function replace($value, $object){
        // clean all matching the filter
        // insert new
    }
    
    public function getSubNode($key){
        
        if($key == null) return $this;
        
        if(!empty($this->collection) && is_array($this->collection[0]) && array_key_exists($key, $this->collection[0])){
            
            $parent = $this;
            
            return (new CollectionValue())->setParent($this)->setWrite(function($value, &$object) use ($key, $parent){
                
                $collection = Collection::filterCollection($parent->filter, collect($parent->collection), $object);
                
                $result = [];
                
                foreach($collection as $o){
                    
                    $o[$key]->write($value, $object);
                    
                    
                }
                
            })->setRead(function(&$object) use ($key, $parent){
                
                $collection = Collection::filterCollection($parent->filter, collect($parent->collection), $object);
                
                $result = [];
                
                foreach($collection as $o){
                    $result = AttributeMapping::ensureAttributeMappingObject($o);
                }
                
                return $result;
                
            });
            
        }
        
    }
    
    public static function filterCollection($scimFilter, $collection, $resourceObject){
        
        if($scimFilter == null){
            return $collection;
        }
        
        $attribute = $scimFilter->attributePath->attributeNames[0];
        $operator = $scimFilter->operator;
        $compareValue = $scimFilter->compareValue;
        
        $result = [];
        
        foreach( $collection->toArray() as $value){
            $result[] = AttributeMapping::ensureAttributeMappingObject($value)->read($resourceObject);
        }
        
        $collectionOriginal = $collection;
        
        $collection = collect($result);
                        
        switch($operator){
             
            case "eq":
                /** @var $collection Coll */
                $result = $collection->where($attribute,'==',$compareValue);
                break;
            case "ne":
                $result = $collection->where($attribute,'<>',$compareValue);
                break;
            case "co":
                //TODO: escape % characters etc, require min length
                throw new SCIMException('co not supported');
                break;
            case "sw":
                //TODO: escape % characters etc, require min length
                throw new SCIMException('sw not supported');
                break;
            case "ew":
                //TODO: escape % characters etc, require min length
                throw new SCIMException('ew not supported');
                break;
            case "pr":
                //TODO: Check for existence for complex attributes
                $result = $collection->where($attribute,'!=',null);
                break;
            case "gt":
                $result = $collection->where($attribute,'>',$compareValue);
                break;
            case "ge":
                $result = $collection->where($attribute,'>=',$compareValue);
                break;
            case "lt":
                $result = $collection->where($attribute,'<',$compareValue);
                break;
            case "le":
                $result = $collection->where($attribute,'<=',$compareValue);
                break;
            default:
                die("Not supported!!");
                break;
                 
        }
        
        foreach($collectionOriginal->keys()->all() as $key){
            if(!in_array($key, (array)$result->keys()->all())){
                unset($collectionOriginal[$key]);
            }
        }
        
        return $collectionOriginal;
        
    }
    
    /**
     * Get an operator checker callback.
     *
     * @param  string  $key
     * @param  string  $operator
     * @param  mixed  $value
     * @return \Closure
     */
    protected function operatorForWhere($key, $operator, $value = null)
    {
        if (func_num_args() == 2) {
            $value = $operator;
    
            $operator = '=';
        }
    
        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);
    
            $strings = array_filter([$retrieved, $value], function ($value) {
                return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
            });
    
                if (count($strings) < 2 && count(array_filter([$retrieved, $value], 'is_object')) == 1) {
                    return in_array($operator, ['!=', '<>', '!==']);
                }
    
                switch ($operator) {
                    default:
                    case '=':
                    case '==':  return $retrieved == $value;
                    case '!=':
                    case '<>':  return $retrieved != $value;
                    case '<':   return $retrieved < $value;
                    case '>':   return $retrieved > $value;
                    case '<=':  return $retrieved <= $value;
                    case '>=':  return $retrieved >= $value;
                    case '===': return $retrieved === $value;
                    case '!==': return $retrieved !== $value;
                }
        };
    }
    
    public function withFilteraa($filter){
         
        if($filter == null){
            
            return $this;
            
        }else{
            
            //collect($this->collection)
            
            
        }
        
         
        // 	    return new AttributeMapping($this->eloquentAttribute,$this->read,$this->write,$this,$filter);
         
        // return an AttributeConfig for which the read and write operations will apply to the corect things
         
        // 	    $read = $this->read();
         
        // 	    if(is_array($read)){
         
        // 	        // and
        // 	           // take overlap of
        //     	           // collect($read)->where('test', 'test','test');
        // 	               // collect($read)->where('2', '2','2');
    
        // 	        // or
        //     	        // take union of
        //         	        // collect($read)->where('test', 'test','test');
        //         	        // collect($read)->where('2', '2','2');
         
        // 	        // TODO: populate where with 'scimFilterToLaravelQuery'
         
        // 	    }else{
        // 	        // ???
        // 	    }
         
        //return new AttributeMapping($this->eloquentAttribute,$this->read,)
    	  
    }
    
}