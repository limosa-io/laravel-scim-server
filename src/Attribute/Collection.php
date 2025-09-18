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

    public function __construct($name, $attribute = null)
    {
        parent::__construct($name);

        $this->attribute = $attribute ?? $name;
    }

    public function getRelationshipName(): string
    {
        return $this->attribute;
    }

    public function read(&$object, array $attributes = []): ?AttributeValue
    {
        if (!empty($attributes) && !in_array($this->name, $attributes) && !in_array($this->getFullKey(), $attributes)) {
            return null;
        }

        $result = $this->doRead($object, $attributes);

        return new AttributeValue($result);
    }

    protected function doRead(&$object, $attributes = [])
    {
        $result = [];

        if ($object->{$this->attribute} !== null) {
            foreach ($object->{$this->attribute} as $o) {
                $element = [];

                foreach ($this->subAttributes as $attribute) {
                    if (($r = $attribute->read($o)) != null) {
                        $element[$attribute->name] = $r->value;
                    }
                }

                $result[] = $element;
            }
        }

        return $result;
    }

    public function applyComparison(Builder &$query, Path $path, Path $parentAttribute = null)
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
