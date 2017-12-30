<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class ReadOnlyAttributeMapping extends AttributeMapping {
	
	public function write($value, &$object) {
		//throw new SCIMException("Write of \"" . json_encode($this->eloquentAttribute) . "\" is not supported",302);
	}
	
}