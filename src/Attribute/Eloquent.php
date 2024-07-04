<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Builder;
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

    public function applyComparison(Builder &$query, Path $path, $parentAttribute = null)
    {
        $attribute = $this->attribute;

        // FIXME: ugly and perhaps incorrect.
        if($parentAttribute != null){
            $attribute = $parentAttribute . '.' . $attribute;
        }

        $operator = $path->node->operator;
        $value = $path->node->compareValue;

        switch ($operator) {
            case "eq":
                $query->where($attribute, $value);
                break;
            case "ne":
                $query->where($attribute, '<>', $value);
                break;
            case "co":
                $query->where($attribute, 'like', '%' . addcslashes($value, '%_') . '%');
                break;
            case "sw":
                $query->where($attribute, 'like', addcslashes($value, '%_') . '%');
                break;
            case "ew":
                $query->where($attribute, 'like', '%' . addcslashes($value, '%_'));
                break;
            case "pr":
                $query->whereNotNull($attribute);
                break;
            case "gt":
                $query->where($attribute, '>', $value);
                break;
            case "ge":
                $query->where($attribute, '>=', $value);
                break;
            case "lt":
                $query->where($attribute, '<', $value);
                break;
            case "le":
                $query->where($attribute, '<=', $value);
                break;
            default:
                throw new SCIMException("Unknown operator " . $operator);
        }
    }


    public function setRelationship($relationship)
    {
        $this->relationship = $relationship;

        return $this;
    }
}
