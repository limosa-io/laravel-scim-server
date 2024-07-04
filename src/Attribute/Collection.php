<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Collection extends AbstractComplex
{
    protected $attribute;
    protected $multiValued = true;

    public function __construct($name, $attribute = null, $schemaNode = false)
    {
        parent::__construct($name, $schemaNode);

        $this->attribute = $attribute ?? $name;
    }

    public function read(&$object)
    {
        $result = [];

        foreach ($object->{$this->attribute} as $o) {
            $element = [];

            foreach ($this->subAttributes as $attribute) {
                $element[$attribute->name] = $attribute->read($o);
            }

            $result[] = $element;
        }

        return $result;
    }

    public function applyComparison(Builder &$query, Path $path, $parentAttribute = null)
    {
        if ($path == null || empty($path->getAttributePathAttributes())) {
            throw new SCIMException('No attribute path attributes found. Could not apply comparison in ' . $this->getFullKey());
        }

        $attribute = $this->getSubNode($path->getAttributePathAttributes()[0]);

        $query->whereHas(
            $this->attribute,
            fn (Builder $query) => $attribute->applyComparison($query, $path->shiftAttributePathAttributes(), $this->attribute)
        )->get();
    }

    public function replace($value, Model &$object, Path $path = null)
    {
        // ignore replace actions
    }
}
