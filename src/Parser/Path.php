<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use Tmilos\ScimFilterParser\Ast\Factor;

class Path implements \Stringable {
    public $attributePath;
    public $valuePath;

    public function __construct(public $node, public $text){
        // attribute path
        $getAttributePath = (fn() => $this->attributePath);

        $attributePath = $getAttributePath->call($this->node);
        $this->attributePath = $attributePath != null ? new AttributePath($attributePath) : null;

        //value path
        $getValuePath = (fn() => $this instanceof Factor ? null : $this->valuePath);

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
            // The line below isp probably not needed
            // $this->setValuePath(null);
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

    public function __toString(): string
    {
        return (string) $this->text;
    }

    public function isNotEmpty(){
        return
            !empty($this->getAttributePathAttributes()) ||
            !empty($this->getValuePathAttributes()) ||
            $this->getValuePathFilter() != null;
    }
}