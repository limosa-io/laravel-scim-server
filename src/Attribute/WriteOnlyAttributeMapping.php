<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class WriteOnlyAttributeMapping extends AttributeMapping {
	
	public function read(&$object) {
		return null;
		//throw new SCIMException("Write of \"" . json_encode($this->eloquentAttribute) . "\" is not supported",302);
	}
	
	public function isReadSupported(){
	    return false;
	}
	
	public function isWriteSupported(){
	    return true;
	}
	
}