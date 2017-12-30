<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;

class Helper{
	
    public static function getMappedValue(&$user, $valueMapping){

        $v = $valueMapping;
		
        if($v instanceof AttributeMapping){
            
            $v = $valueMapping->read($user);

        }
        
        if(is_scalar($v)){
            
            $v = $v;

        }else if( is_array($v) ){
            
            $vNew = []; 
            
            foreach($v as $j => $l){
                
                $result = Helper::getMappedValue($user,$l);

                if($result != null){
                    $vNew[$j] = $result;
                }

            }
            
            $v = $vNew;

        }else if($v instanceof AttributeMapping){
            $v = Helper::getMappedValue($user,$v);
        }

        return $v;

    }
    
    public static function isAssoc($array){

        return (array_values($array) !== $array);

    }


}