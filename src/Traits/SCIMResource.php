<?php

namespace ArieTimmerman\Laravel\SCIMServer\Traits;

use \Illuminate\Database\Eloquent\JsonEncodingException;
use ArieTimmerman\Laravel\SCIMServer\Helper;

trait SCIMResource {
	
	public function __call($function, $args = []){
		return parent::__call($function, $args);
	}
	
    public function toSCIMJson($options = 0){
        
        $json = json_encode($this->toSCIMArray(), $options);
        
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;

    }
    
    public function getSCIMVersion(){
        //implement optionally
    }
    
    //TODO: is this needed?
    public function toArray_fromParent(){
        return parent::toArray();
    }
    
    //TODO: is this needed?
    public function toJson($options = 0){
    	return $this->toSCIMJson($options);
    }


}