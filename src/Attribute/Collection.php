<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Builder;

class Collection extends Attribute
{
    protected $attribute;
    protected $subAttributes;

    public function __construct($name, $attribute = null, $schemaNode = false)
    {
        parent::__construct($name, $schemaNode);

        $this->attribute = $attribute ?? $name;
    }

    public function withSubAttributes(...$subAttributes)
    {
        foreach ($subAttributes as $attribute) {
            $attribute->setParent($this);
        }

        $this->subAttributes = $subAttributes;

        return $this;
    }

    public function read(&$object)
    {
        $result = [];

        foreach ($object->{$this->attribute} as $o) {

            $element = [];

            foreach($this->subAttributes as $attribute){
                $element[$attribute->name] = $attribute->read($o);
            }

            $result[] = $element;
        }

        return $result;
    }

    public function getSubNode(string $key): ?Attribute
    {
        return collect($this->subAttributes)->first(fn ($element) => $element->name == $key);
    }

    public function applyComparison(Builder &$query, Path $path, $parentAttribute = null){

        if($path == null || empty($path->getAttributePathAttributes())){
            throw new SCIMException('No attribute path attributes found. Could not apply comparison in ' . $this->getFullKey());
        }

        $attributeNames = $path->getAttributePathAttributes()[0];
        $attribute = $this->getSubNode($attributeNames);    

        $query->whereHas(
            $this->attribute,
            fn (Builder $query) => $attribute->applyComparison($query, $path->shiftAttributePathAttributes(), $this->attribute)
        )->get();
    }

}
