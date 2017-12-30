<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;

class Complex extends AttributeMapping{
    
    public function subWhere($query){
        
        // select * from emails where emails.user_id=1  and ...
        // or simply 
        
    }
    
}