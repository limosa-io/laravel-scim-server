<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use Illuminate\Support\Carbon;
class AttributeMapping {
	
	public $eloquentAttribute, $read, $write;
	
	function __construct($eloquentAttribute, $read = null, $write = null) {
		$this->eloquentAttribute = $eloquentAttribute;
		
		if($read == null){
			$read = function(&$object) {
			    
			    $result = $object->{$this->eloquentAttribute};
			    
			    return self::eloquentAttributeToString($result); 
			    
			};
		}
		
		if($write == null){
			$write = function($value, &$object) { 
			    $object->{$this->eloquentAttribute} = $value; 
			};
		}
		
		$this->read = $read;
		$this->write = $write;
		
	}
	
	public function getSortAttribute(){
	    return $this->eloquentAttribute;
	}
	
	public function write($value, &$object) {
		return ($this->write)($value, $object);
	}
	
	public function read(&$object) {
		return ($this->read)($object);
	}
	
	/**
	 * Returns the AttributeMapping for a specific value. Uses for example for creating queries ... and sorting
	 * @param unknown $value
	 * @return \ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping
	 */
	public function getMapping($value){
	    return $this;
	}
	
	public static function eloquentAttributeToString($value){
	    
	    if($value instanceof \Carbon\Carbon){
	        $value = $value->format('c');
	    }
	    
	    return $value;
	}
	
}