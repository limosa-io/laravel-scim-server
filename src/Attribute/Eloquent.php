<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Model;

class Eloquent extends Attribute
{
    protected $attribute;
    public $relationship;

    public function __construct($name, $attribute = null, $schemaNode = false)
    {
        parent::__construct($name, $schemaNode);

        $this->attribute = $attribute ?? $name;
        $this->setSortAttribute($this->attribute);
    }

    public function read(&$object)
    {
        $value = $object->{$this->attribute};

        if ($value instanceof \Carbon\Carbon) {
            $value = $value->format('c');
        }
        return $value;
    }

    public function add($value, Model &$object)
    {
        $object->{$this->attribute} = $value;
        $this->dirty = true;
    }

    public function replace($value, Model &$object, $path = null)
    {
        $object->{$this->attribute} = $value;
        $this->dirty = true;
    }

    public function patch($operation, $value, Model &$object, ?Path $path = null)
    {
        if ($path->isNotEmpty()) {
            throw new SCIMException('path operation not support for eloquent type attributes');
        }

        if ($operation == 'replace' || $operation == 'add') {
            $object->{$this->attribute} = $value;
            $this->dirty = true;
        } elseif ($operation == 'remove') {
            $object->{$this->attribute} = null;
            $this->dirty = true;
        }
    }

    public function getSortAttributeByPath(Path $path)
    {
        if ($path->getValuePath() != null) {
            throw new SCIMException('Incorrect sortBy parameter');
        }

        return $this->attribute;
    }

    public function applyWhereCondition(&$query, $operator, $value)
    {
        if ($this->relationship != null) {
            $query->whereHas(
                $this->relationship,
                fn ($query) => $this->applyWhereConditionDirect($this->attribute, $query, $operator, $value)
            )->get();
        } else {
            $this->applyWhereConditionDirect($this->attribute, $query, $operator, $value);
        }
    }


    public function setRelationship($relationship)
    {
        $this->relationship = $relationship;

        return $this;
    }
}
