<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

class AttributeMapping {
	
	public $eloquentAttribute, $read, $write;
	
	function __construct($eloquentAttribute, $read = null, $write = null) {
		$this->eloquentAttribute = $eloquentAttribute;
		
		if($read == null){
			$read = function(&$object) { return self::readDefault($object); };
		}
		
		if($write == null){
			$write = function($value, &$object) { return self::writeDefault($value, $object); };
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
	
	protected function readDefault(&$object) {
		return $object->{$this->eloquentAttribute};
	}
	
	protected function writeDefault($value, &$object) {
		$object->{$this->eloquentAttribute} = $value;
		
	}
	
	/**
	 * Returns the AttributeMapping for a specific value. Uses for example for creating queries ... and sorting
	 * @param unknown $value
	 * @return \ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping
	 */
	public function getMapping($value){
	    return $this;
	}
	
}