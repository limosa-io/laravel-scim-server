<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;

//TODO: Inject an instance of this class to methods in ResourceController
class ResourceType{
    
    protected $configuration = null, $name = null;
    
    function __construct($name, $configuration){
        $this->configuration = $configuration;
    }
    
    public function getConfiguration(){
        return $this->configuration;
    }
    
    public function getMapping(){
        return $this->configuration['mapping'];
    }
    
    public function getName(){
        return $this->name;
    }
    
    public function getClass(){
        return $this->configuration['class'];
    }
    
}