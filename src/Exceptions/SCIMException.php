<?php

namespace ArieTimmerman\Laravel\SCIMServer\Exceptions;

use Exception;

class SCIMException extends Exception{
	
	protected $scimType = null;
	
	//TODO: What if more than two parameters are provided?
	function __construct($message, $code = 404, $scimType = "invalidValue"){
		parent::__construct($message,$code);
		
		$this->scimType = $scimType;
	}
	
	public function render($request){
		return response(new \ArieTimmerman\Laravel\SCIMServer\SCIM\Error($this->getMessage(),$this->getCode(),$this->scimType),$this->getCode());
	}
	
}