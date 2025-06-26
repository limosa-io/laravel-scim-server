<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

abstract class AbstractComplex extends Attribute
{
    /**
     * @var Attribute[]
     */
    public $subAttributes = [];

    public function getSchemaNode(): ?Schema
    {
        if ($this->parent != null) {
            return null;
        }

        return collect($this->subAttributes)->first(fn ($element) => $element instanceof Schema);
    }

    public function getSchemaNodes(){
        return collect($this->subAttributes)->filter(fn ($element) => $element instanceof Schema)->values()->toArray();
    }

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
        $result = collect($this->subAttributes)->first(fn ($element) => $element->name == $key);

        // if this is the root node, search for a subNode in all of the default schema nodes
        if ($result == null) {
            foreach ($this->getSchemaNodes() as $schema) {
                if ($schema->getSubNode($key)) {
                    $result = $schema->getSubNode($key);
                    continue;
                }
            }
        }

        return $result;
    }

    public function generateSchema()
    {
        $base = parent::generateSchema();

        $base['subAttributes'] = collect($this->subAttributes)->map(fn ($element) => $element->generateSchema())->toArray();

        return $base;
    }
}
