<?php

namespace ArieTimmerman\Laravel\SCIMServer\Traits;

use \Illuminate\Database\Eloquent\JsonEncodingException;
use ArieTimmerman\Laravel\SCIMServer\Helper;

trait SCIMResource {
	
	public function __call($function, $args = []){
		
		//scimGetEloquentSortAttribute($scimAttribute);
		//scimGetAttributeValue($scimAttribute)
		//scimWriteAttributeValue($scimAttribute, $scimValue)
		
		return parent::__call($function, $args);
	}
	
    public function toSCIMJson($options = 0){
        
        $json = json_encode($this->toSCIMArray(), $options);
        
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;

    }

    
    //TODO: Move this method to Helper class. Allows use of this method outside Trait
   
//     public function toArray(){
//     	return Helper::objectToSCIMArray($this);
//     }
    
    public function toArray_fromParent(){
        return parent::toArray();
    }
    
    public function toJson($options = 0){
    	return $this->toSCIMJson($options);
    }


}