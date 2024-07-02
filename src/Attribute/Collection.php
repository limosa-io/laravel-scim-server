<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Database\Eloquent\Model;
use Tmilos\ScimSchema\Model\Resource;

class Collection extends Attribute
{
    public $collection = [];

    public function __construct($name = null, $collection = [])
    {
        $this->name = $name;
        $this->collection = $collection;
    }

    public function read(&$object)
    {
        $result = [];

        foreach ($this->collection as $attribute) {
            $result[] = $attribute->read($object);
        }

        return $result;
    }

    public function add($value, Model &$object)
    {

        //only for creation requests
        if ($object->id == null) {
            foreach ($value as $key => $v) {
                $this->getSubNode($key)->add($v, $object);
            }
        } else {
            foreach ($value as $key => $v) {
                if ($this->getSubNode($key) != null) {
                    $this->getSubNode($key)->add($v, $object);
                } else {
                    //TODO: log ignore
                }
            }

            // throw (new SCIMException('Add is not implemented for updates of ' . $this->getFullKey()))->setCode(501);
        }
    }

    public function remove($value, Model &$object)
    {
        foreach ($this->collection as $c) {
            if ($c->isWriteSupported()) {
                $c->remove($value, $object);
            }
        }
    }

    public function replace($value, Model &$object)
    {
        $this->remove($value, $object);

        $this->add($value, $object);
    }

    public function getEloquentAttributes()
    {
        $result = $this->eloquentAttributes;

        foreach ($this->collection as $value) {
            $result = array_merge($result, $value->getEloquentAttributes());
        }

        return $result;
    }

    public function getSubNode($key, $schema = null)
    {
        if ($key == null) {
            return $this;
        }

        if (!empty($this->collection) && is_array($this->collection[0]) && array_key_exists($key, $this->collection[0])) {
            $parent = $this;

            return (new CollectionValue())
                ->setEloquentAttributes($this->collection[0][$key]->getEloquentAttributes())
                ->setKey($key)
                ->setParent($this)
                ->setAdd(function ($value, &$object) use ($key, $parent) {
                    $collection = Collection::filterCollection($parent->filter, collect($parent->collection), $object);

                    $result = [];

                    foreach ($collection as $o) {
                        $o[$key]->add($value, $object);
                    }
                })
                ->setReplace(function ($value, &$object) use ($key, $parent) {
                    $collection = Collection::filterCollection($parent->filter, collect($parent->collection), $object);

                    $result = [];

                    foreach ($collection as $o) {
                        $o[$key]->add($value, $object);
                    }
                })
                ->setRead(function (&$object) use ($key, $parent) {
                    $collection = Collection::filterCollection($parent->filter, collect($parent->collection), $object);

                    $result = [];

                    foreach ($collection as $o) {
                        $result = AttributeMapping::ensureAttributeMappingObject($o);
                    }

                    return $result;
                })
                ->setSchema($schema);
        }
    }

    public static function filterCollection($scimFilter, $collection, $resourceObject)
    {
        if ($scimFilter == null) {
            return $collection;
        }

        $attribute = $scimFilter->attributePath->attributeNames[0];
        $operator = $scimFilter->operator;
        $compareValue = $scimFilter->compareValue;

        $result = [];

        foreach ($collection->toArray() as $value) {
            $result[] = AttributeMapping::ensureAttributeMappingObject($value)->read($resourceObject);
        }

        $collectionOriginal = $collection;

        $collection = collect($result);

        switch ($operator) {
            case "eq":
                /**
                 * @var $collection Coll
                */
                $result = $collection->where($attribute, '==', $compareValue);
                break;
            case "ne":
                $result = $collection->where($attribute, '<>', $compareValue);
                break;
            case "co":
                throw (new SCIMException(sprintf('"co" is not supported for attribute "%s"', $this->getFullKey())))->setCode(501);
                    break;
            case "sw":
                throw (new SCIMException(sprintf('"sw" is not supported for attribute "%s"', $this->getFullKey())))->setCode(501);
                    break;
            case "ew":
                throw (new SCIMException(sprintf('"ew" is not supported for attribute "%s"', $this->getFullKey())))->setCode(501);
                    break;
            case "pr":
                $result = $collection->where($attribute, '!=', null);
                break;
            case "gt":
                $result = $collection->where($attribute, '>', $compareValue);
                break;
            case "ge":
                $result = $collection->where($attribute, '>=', $compareValue);
                break;
            case "lt":
                $result = $collection->where($attribute, '<', $compareValue);
                break;
            case "le":
                $result = $collection->where($attribute, '<=', $compareValue);
                break;
            default:
                die("Not supported!!");
                    break;
        }

        foreach ($collectionOriginal->keys()->all() as $key) {
            if (!in_array($key, (array)$result->keys()->all())) {
                unset($collectionOriginal[$key]);
            }
        }

        return $collectionOriginal;
    }

    /**
     * Get an operator checker callback.
     *
     * @param  string $key
     * @param  string $operator
     * @param  mixed  $value
     * @return \Closure
     */
    protected function operatorForWhere($key, $operator, $value = null)
    {
        if (func_num_args() == 2) {
            $value = $operator;

            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            $strings = array_filter(
                [$retrieved, $value],
                function ($value) {
                    return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
                }
            );

            if (count($strings) < 2 && count(array_filter([$retrieved, $value], 'is_object')) == 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }

            switch ($operator) {
                default:
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
            }
        };
    }

    public function applyWhereCondition(&$query, $operator, $value)
    {
        throw (new SCIMException(sprintf('Filter is not supported for attribute "%s"', $this->getFullKey())))->setCode(501);
    }
}
