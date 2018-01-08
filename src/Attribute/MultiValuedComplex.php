<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Attribute\ConstantAttributeMapping;

class MultiValuedComplex extends ConstantAttributeMapping{
    
    public function withFilter($filter){
        
        return new MultiValuedComplex($this->eloquentAttribute,$this->read,$this->write,$this,$filter);
        
    }
    
    public function getSubNode($key){
        
        return $this->eloquentAttribute[$key];
    }
    
    public function write($value, &$object){
        
        
        
    }
    
    
    
}