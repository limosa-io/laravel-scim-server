<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use Tmilos\ScimFilterParser\Ast\ValuePath as ScimFilterParserAstValuePath;

class ValuePath {
    public $path;

    public $attributePath = null;
    public $filter = null;

    public function __construct(ScimFilterParserAstValuePath $path){
        $this->path = $path;

        $getAttributePath = function () {
            return $this->attributePath;
        };

        $this->attributePath = new AttributePath($getAttributePath->call($this->path));

        $getFilter = function () {
            return $this->filter;
        };

        $this->filter = new Filter($getFilter->call($this->path));

    }
    
    public function getAttributePath(): AttributePath{
        return $this->attributePath;
    }

    public function setAttributePath(AttributePath $attributePath){
        $this->attributePath = $attributePath;
    }

    public function getFilter(): Filter{
        return $this->filter;
    }

    public function setFilter($filter){
        $this->filter = $filter;
    }
}