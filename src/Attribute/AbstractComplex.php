<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

abstract class AbstractComplex extends Attribute
{
    /**
     * @var Attribute[]
     */
    public $subAttributes = [];

    public function getValidations()
    {
        $result = [
            addcslashes($this->getFullKey(),'.') => $this->validations
        ];

        foreach ($this->subAttributes as $attribute) {
            $result = array_merge($result, $attribute->getValidations());
        }

        $result = collect($result)->filter(fn ($v, $k) => !empty($v))->toArray();

        return $result;
    }

    public function withSubAttributes(...$subAttributes)
    {
        foreach ($subAttributes as $attribute) {
            $attribute->setParent($this);
        }

        $this->subAttributes = $subAttributes;

        return $this;
    }

    public function getSubNode(string $key): ?Attribute
    {
        return collect($this->subAttributes)->first(fn ($element) => $element->name == $key);
    }
}
