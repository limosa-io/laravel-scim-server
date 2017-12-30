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

    public function toSCIMArray(){
        
//         $userArray = $this->toArray();

        $result = [];
        
        
        $mapping = config("scimserver.Users.mapping");
        
        foreach($mapping as $key => $value){

            preg_match("/^(.*?)(\\.\\*)?$/",$key,$matches);

            $key = $matches[1];
            
            $v = Helper::getMappedValue($this, $value);
            
            if($v != null){

                if(isset($matches[2]) && !empty($matches[2])){
                    $result[$key] = [];
                    $result[$key][] = $v;
                }else{
                    $result[$key] = $v;
                }

                
            }

        }

        return $result;

    }
    
    public function toArray(){
    	return $this->toSCIMArray();
    }
    
    public function toJson($options = 0){
    	return $this->toSCIMJson($options);
    }


}