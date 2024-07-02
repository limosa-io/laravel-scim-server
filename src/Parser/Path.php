<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;


class Path {
    public $node;
    public $text;
    public $attributePath;
    public $valuePath;

    public function __construct($node, $text){
        $this->node = $node;
        $this->text = $text;

        // attribute path
        $getAttributePath = function () {
            return $this->attributePath;
        };

        $attributePath = $getAttributePath->call($this->node);
        $this->attributePath = $attributePath != null ? new AttributePath($attributePath) : null;

        //value path
        $getValuePath = function () {
            return $this->valuePath;
        };

        $valuePath = $getValuePath->call($this->node);
        $this->valuePath = $valuePath != null ? new ValuePath($valuePath) : null;

    }

    public function getAttributePath(): ?AttributePath{
        return $this->attributePath;
    }

    public function setAttributePath($attributePath){
        $this->attributePath = $attributePath;
    }

    public function getValuePath(): ?ValuePath{
        return $this->valuePath;
    }

    public function setValuePath($valuePath){
        $this->valuePath = $valuePath;
    }

    public function getValuePathAttributes(): array{
        return $this->getValuePath()?->getAttributePath()?->getAttributeNames() ?? [];
    }

    public function getAttributePathAttributes(): array{
        return $this->getAttributePath()?->getAttributeNames() ?? [];
    }

    public function getValuePathFilter(){
        return $this->getValuePath()?->getFilter();
    }

    public function setValuePathFilter($filter){
        return $this->getValuePath()?->setFilter($filter);
    }

    public function shiftValuePathAttributes(): Path {
        $this->getValuePath()->getAttributePath()->shiftAttributeName();

        if(empty($this->getValuePathAttributes())){
            $this->setValuePath(null);
        }

        return $this;
    }

    public function shiftAttributePathAttributes(): Path {
        $this->getAttributePath()->shiftAttributeName();

        if(empty($this->getAttributePathAttributes())){
            $this->setAttributePath(null);
        }

        return $this;
    }

    public function __toString()
    {
        return $this->text;
    }

    public function isNotEmpty(){
        return
            !empty($this->getAttributePathAttributes()) ||
            !empty($this->getAttributePathAttributes()) ||
            $this->getValuePathFilter() != null;
    }
}