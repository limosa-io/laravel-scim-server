<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use Illuminate\Support\Carbon;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class UnmapedAttributeMapping extends AttributeMapping {
	
	public function isReadSupported(){
	    return true;
	}
	
	public function isWriteSupported(){
	    return false;
	}
	
	public function withFilter($filter){
	    throw new SCIMException("Filter not supported for unmapped attributes");
	}
	
}