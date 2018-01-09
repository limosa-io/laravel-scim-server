<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use Illuminate\Support\Carbon;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class AttributeMapping {
	
	public $read, $write;
	
	public $id = null;
	public $parent = null;
	public $filter = null;
	
	public $key = null;
	
	private $readEnabled = true;
	private $writeEnabled = true;
	
	private $sortAttribute = null;
	
	private $mappingAssocArray = null;
	
	public $eloquentAttributes = [];
	
	private $defaultSchema = null, $schema = null;
	
	public static function noMapping($parent = null) : AttributeMapping{
	    return (new AttributeMapping())->disableWrite()->disableRead()->setParent($parent);
	}
	
	public static function arrayOfObjects($mapping, $parent = null) : AttributeMapping{
	    return (new Collection())->setStaticCollection($mapping)->setRead(
	       function (&$object) use ($mapping, $parent){
	           
	           $result = [];
	           
	           foreach($mapping as $key=>$o){
	               $element = self::ensureAttributeMappingObject($o)->setParent($parent)->read($object);
	               
	               if($element != null){
	                   $result[] = $element;
	               }
	           }
	           
	           return empty($result) ? null : $result;
	           
	       }
	    );
	}
	
	public static function object($mapping, $parent = null) : AttributeMapping{
	    
	    return (new AttributeMapping())->setMappingAssocArray($mapping)->setRead(function(&$object) use ($mapping, $parent) {
	        	        
		    $result = [];
		    
		    foreach($mapping as $key=>$value){
		        
                $result[$key] = self::ensureAttributeMappingObject($value)->setParent($parent)->read($object);
                
                if(empty($result[$key])){
                    unset($result[$key]);
                }
		    }
		    
		    return empty($result) ? null : $result;
		    
		});
	}
	
	public static function constant($text, $parent = null) : AttributeMapping{
	    return (new AttributeMapping())->disableWrite()->setParent($parent)->setRead(function(&$object) use ($text) {
		    
		    return $text;
		    
		});
	    
	}
	
	public static function eloquent($eloquentAttribute, $parent = null) : AttributeMapping{
	    
	    return (new AttributeMapping())->setParent($parent)->setRead(function(&$object) use ($eloquentAttribute)  {
	        	        
			    $result = $object->{$eloquentAttribute};
			    
			    return self::eloquentAttributeToString($result); 
			    
			})->setWrite(function($value, &$object) use ($eloquentAttribute) {
			    $object->{$eloquentAttribute} = $value; 
			})->setSortAttribute($eloquentAttribute)->setEloquentAttributes([$eloquentAttribute]);
	}
	
	public function setMappingAssocArray($mapping) : AttributeMapping{
	    $this->mappingAssocArray = $mapping;
	    
	    return $this;
	}
	
	public function setSchema($schema) : AttributeMapping{
	    $this->schema = $schema;
	    return $this;
	}
	
	public function getSchema(){
	    return $this->schema;
	}
	
	public function setDefaultSchema($schema) : AttributeMapping{
	    $this->defaultSchema = $schema;
	    
	    return $this;
	}
	
	public function getDefaultSchema(){
	    return $this->defaultSchema;
	}
	
	public function setEloquentAttributes(array $attributes){
	    $this->eloquentAttributes = $attributes;
	    
	    return $this;
	}
	
	public function getEloquentAttributes(){
	    
	    $result = $this->eloquentAttributes;
	    
	    if($this->mappingAssocArray){
	        foreach($this->mappingAssocArray as $key=>$value){
	            foreach(self::ensureAttributeMappingObject($value)->setParent($this)->getEloquentAttributes() as $attribute){
	                $result[] = $attribute;
	            }
	        }
	    }
	    
	    return $result;
	}
	
	public function disableRead(){
	    $this->read = function(&$object){ /* TODO: throw exception?? */ };
	    $this->readEnabled = false;
	    return $this;
	}
	
	public function disableWrite(){
        $this->write = function($value, &$object){ /* TODO: throw exception?? */ };
        $this->writeEnabled = false;
	    return $this;
	}
	
	public function setRead($read) : AttributeMapping{
	    
	    $this->read = $read;
	    
	    return $this;
	}
	
	public function setWrite($write){
	     
	    $this->write = $write;
	     
	    return $this;
	}
	
	public function setParent($parent){
	    $this->parent = $parent;
	    return $this;
	}
	
	public function setKey($key){
	    $this->key = $key;
	    return $this;
	}
	
	public function getKey(){
	    return $this->key;
	}
	
	public function getFullKey(){
	    
	    $parent = $this->parent;
	    
	    $fullKey = [];
	    
	    while($parent != null){
	        $parentKey = $parent->getKey();
	        
	        $fullKey[] = $parentKey;
	        
	        $parent = $parent->parent;
	    }
	    
	    $fullKey[]  = $this->getKey();
	    
	    //ugly hack
	    $fullKey = array_filter($fullKey, function($value){ return !empty($value); });
	    
	    return implode(".", $fullKey);
	}
	
	public function setFilter($filter){
	    
	    $this->filter = $filter;
	    
	    return $this;
	}
	
	//TODO: remove first argument. Introduce other sorting mechanism
	function __construct() {
		
	    $this->read = function($object){
	        die("not implemented!!");
	    };
	    
	    $this->write = function($value, &$object){
	        die("not implemente write for " . $this->getFullKey().  "!!");
	    };
		
	}
	
	public function setSortAttribute($attribute){
	    $this->sortAttribute = $attribute;
	    
	    return $this;
	}
	
	public function getSortAttribute(){
	    return $this->sortAttribute;
	}
	
	public function withFilter($filter){
	    
        return $this->setFilter($filter);
	  
	    
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
	
	public function add($value, &$object) {
	    return $this->write($value, $object);
	}
	
	public function replace($value, &$object) {
	    
	    $current = $this->read($object);
	    
	    //TODO: Really implement replace ...???
	    
	    return $this->write($value, $object);
	}
	
	public function remove(&$object) {
	    
	    //TODO: implement remove for multi valued attributes 
	    return $this->write(null, $object);
	    
	}
	
	public function write($value, &$object) {
		return ($this->write)($value, $object);
	}
	
	public function read(&$object) {
		return ($this->read)($object);
	}	
	
	public static function eloquentAttributeToString($value){
	    
	    if($value instanceof \Carbon\Carbon){
	        $value = $value->format('c');
	    }
	    
	    return $value;
	}
	
	public function isReadSupported(){
	    return $this->readEnabled;
	}
	
	public function isWriteSupported(){
	    return $this->writeEnabled;
	}
	
	public static function ensureAttributeMappingObject($attributeMapping, $parent = null) : AttributeMapping{
	    
	   $result = null;
	   
	   if($attributeMapping == null){
            $result = self::noMapping($parent);
        }else if (is_array($attributeMapping) && !empty($attributeMapping) && isset($attributeMapping[0]) ){
            $result = self::arrayOfObjects($attributeMapping, $parent);
        }else if(is_array($attributeMapping)){
            $result = self::object($attributeMapping, $parent);
        }else if ($attributeMapping instanceof AttributeMapping){
            $result = $attributeMapping->setParent($parent);
        }else{
            throw new SCIMException("not ok!");
        }
        
        return $result;
	    
	}
	
	/**
	 * Returns the AttributeMapping for a specific value. Uses for example for creating queries ... and sorting
	 * @param unknown $value
	 * @return \ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping
	 */
	public function getSubNode($key){
	     
	    if($key == null) return $this;
	     
	    if($this->mappingAssocArray != null && array_key_exists($key,$this->mappingAssocArray)){
	        return self::ensureAttributeMappingObject($this->mappingAssocArray[$key])->setParent($this)->setKey($key);
	    }else{
	        throw new SCIMException("No mapping!");
	    }
	     
	}
	
	public function getNode($attributePath){
	    
	    if($attributePath == null){
	        return $this;
	    }
	    
	    $schema = $attributePath->schema;
	    	    
	    if(!empty($schema) && !empty($this->getSchema()) && $this->getSchema() != $schema){
	        new SCIMException("Schema conflict!");
	    }
	    
	    $elements = [];
	    
	    if(empty($this->getSchema())){
	        $elements[] = $schema ?? $this->getDefaultSchema();
	    }
	    
	    foreach($attributePath->attributeNames as $a){
	        $elements[] = $a;
	    }
	    
	    $node = $this;
	    
        foreach($elements as $element){
            $node = $node->getSubNode($element);
        }
        
        return $node;	    
	    
	}
	
	public function getSubNodeWithPath($path){
	    
	    if($path == null){
	        return $this;
	    }else{
	        
	        $getAttributePath = function() {
	            return $this->attributePath;
	        };
	
	        $getValuePath = function() {
	            return $this->valuePath;
	        };
	
	        $getFilter = function() {
	            return $this->filter;
	        };
	
	        return $this->getNode( @$getAttributePath->call($getValuePath->call($path)) )->withFilter( @$getFilter->call($getValuePath->call($path)) )->getNode( $getAttributePath->call($path) );
	
	    }
	
	    return $result;
	}
	
}