<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use Tmilos\ScimFilterParser\Ast\AttributePath as AstAttributePath;

class AttributePath {
    public $path;

    public function __construct(AstAttributePath $path){
        $this->path = $path;
    }
    
    public function getAttributeNames(){
        return $this->path->attributeNames;
    }

    public function shiftAttributeName(){
        return array_shift($this->path->attributeNames);
    }
}
