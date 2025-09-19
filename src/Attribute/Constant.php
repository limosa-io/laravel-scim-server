<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;

class Constant extends Attribute
{
    protected $value;

    public function __construct($name, $value)
    {
        parent::__construct($name);

        $this->value = $value;
    }

    protected function doRead(&$object, $attributes = [])
    {
        return $this->value;
    }

    public function add($value, &$object)
    {
        return $this->replace($value, $object);
    }

    public function replace($value, &$object, $path = null)
    {
        $current = json_encode($this->read($object)?->value);

        if (json_encode($value) != $current) {
            throw (new SCIMException(sprintf('Write to "%s" is not supported, tried to change "%s" to "%s"', $this->getFullKey(), $current, json_encode($value))))->setCode(403)->setScimType('mutability');
        }

        $this->dirty = true;
    }

    public function patch($operation, $value, Model &$object, ?Path $path = null)
    {
        throw new SCIMException('Patch operation not supported for constant attributes');
    }

    public function applyComparison(Builder &$query, Path $path, $parentAttribute = null)
    {
        $operator = $path->node->operator ?? null;
        $value = $path->node->compareValue ?? null;

        if ($operator === null) {
            throw new SCIMException('Invalid comparison on constant attribute');
        }

        $constantValue = $this->value;

        $matches = $this->valuesAreEqual($constantValue, $value);

        switch ($operator) {
            case 'pr':
                $query->whereRaw('1 = 1');
                return;

            case 'eq':
                $query->whereRaw($matches ? '1 = 1' : '1 = 0');
                return;

            case 'ne':
                $query->whereRaw($matches ? '1 = 0' : '1 = 1');
                return;

            default:
                throw new SCIMException(sprintf('Operator "%s" not supported for constant attributes', $operator));
        }
    }

    private function valuesAreEqual($constantValue, $compareValue): bool
    {
        if (is_string($constantValue) && is_string($compareValue)) {
            return strcasecmp($constantValue, $compareValue) === 0;
        }

        return $constantValue === $compareValue;
    }
}
