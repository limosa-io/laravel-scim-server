<?php

namespace ArieTimmerman\Laravel\SCIMServer\Exceptions;

use Exception;

class SCIMException extends Exception{
	
	protected $scimType = "invalidValue";
	protected $httpCode = 404;
	
	function __construct($message){
		parent::__construct($message);
	}
	
	public function setScimType($scimType) : SCIMException{
	    $this->scimType = $scimType;
	    
	    return $this;
	}
	
	public function setCode($code) : SCIMException{
	    $this->httpCode = $code;
	    
	    return $this;
	}
	
	public function render($request){
		return response(new \ArieTimmerman\Laravel\SCIMServer\SCIM\Error($this->getMessage(),$this->httpCode,$this->scimType),$this->httpCode);
	}
	
}