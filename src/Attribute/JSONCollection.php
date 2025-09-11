<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JSONCollection extends MutableCollection
{
    #[\Override]
    public function add($value, Model &$object)
    {
        foreach ($value as $v) {
            if (collect($object->{$this->attribute})->contains(
                fn ($item) => collect($item)->diffAssoc($v)->isEmpty()
            )) {
                throw new SCIMException('Value already exists', 400);
            }
        }
        $object->{$this->attribute} = collect($object->{$this->attribute})->merge($value);
    }

    #[\Override]
    public function replace($value, Model &$object, ?Path $path = null)
    {
        $object->{$this->attribute} = $value;
    }

    #[\Override]
    public function doRead(&$object, $attributes = [])
    {
        return $object->{$this->attribute}?->values()->all();
    }

    #[\Override]
    public function remove($value, Model &$object, ?Path $path = null)
    {
        if ($path?->getValuePathFilter()?->getComparisonExpression() != null) {
            $attributes = $path?->getValuePathFilter()?->getComparisonExpression()?->attributePath?->attributeNames;
            $operator = $path?->getValuePathFilter()?->getComparisonExpression()?->operator;
            $compareValue = $path?->getValuePathFilter()?->getComparisonExpression()?->compareValue;

            if ($value != null) {
                throw new SCIMException('Non-null value is currently not supported for remove operation with filter');
            }

            if (count($attributes) != 1) {
                throw new SCIMException('Only one attribute is currently supported for remove operation with filter');
            }

            $object->{$this->attribute} = collect($object->{$this->attribute})->filter(function ($item) use ($attributes, $operator, $compareValue) {
                // check operator eq and ne
                if ($operator == 'eq') {
                    return !(isset($item[$attributes[0]]) && $item[$attributes[0]] == $compareValue);
                } elseif ($operator == 'ne') {
                    return !(!isset($item[$attributes[0]]) || $item[$attributes[0]] != $compareValue);
                } else {
                    throw new SCIMException('Unsupported operator for remove operation with filter');
                }
            })->values()->all();
        } else {
            foreach ($value as $v) {
                $object->{$this->attribute} = collect($object->{$this->attribute})->filter(fn($item) => !collect($item)->diffAssoc($v)->isEmpty())->values()->all();
            }
        }
    }

    #[\Override]
    public function applyComparison(Builder &$query, Path $path, ?Path $parentAttribute = null)
    {
        $fieldName = 'value';

        if ($path != null && !empty($path->getAttributePathAttributes())) {
            $fieldName = $path->getAttributePathAttributes()[0];
        }

        $operator = $path->node->operator;
        $value = $path->node->compareValue;

        $exists = false;

        foreach ($this->subAttributes as $subAttribute) {
            if ($subAttribute->name == $fieldName) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            throw new SCIMException('No attribute found with name ' . $path->getAttributePathAttributes()[0]);
        }

        // check if engine is postgres
        if (DB::getConfig("driver") == 'pgsql') {
            $baseQuery = sprintf("EXISTS (
                SELECT 1 
                FROM json_array_elements(%s) elem
                WHERE elem ->> '%s' LIKE ?
            )", $this->attribute, $fieldName);
        } elseif (DB::getConfig("driver") == 'sqlite') {
            $baseQuery = sprintf("EXISTS (
                SELECT 1
                FROM json_each(%s) AS elem
                WHERE json_extract(elem.value, '$.%s') LIKE ?
            )", $this->attribute, $fieldName);
        } else {
            throw new SCIMException('Unsupported database engine');
        }

        switch ($operator) {
            case "eq":
                $query->whereRaw($baseQuery, [addcslashes((string) $value, '%_')]);
                break;
            case "ne":
                if (DB::getConfig("driver") == 'pgsql') {
                    $baseQuery = sprintf("EXISTS (
                            SELECT 1 
                            FROM json_array_elements(%s) elem
                            WHERE elem ->> '%s' NOT LIKE ?
                        )", $this->attribute, $fieldName);
                } elseif (DB::getConfig("driver") == 'sqlite') {
                    $baseQuery = sprintf("EXISTS (
                            SELECT 1
                            FROM json_each(%s) AS elem
                            WHERE json_extract(elem.value, '$.%s') NOT LIKE ?
                        )", $this->attribute, $fieldName);
                } else {
                    throw new SCIMException('Unsupported database engine');
                }
                $query->whereRaw($baseQuery, [addcslashes((string) $value, '%_')])->orWhereNull($this->attribute);
                break;
            case "co":
                $query->whereRaw($baseQuery, ['%' . addcslashes((string) $value, '%_') . "%"]);
                break;
            case "sw":
                // $query->where($jsonAttribute, 'like', addcslashes($value, '%_') . '%');
                $query->whereRaw($baseQuery, [addcslashes((string) $value, '%_') . "%"]);
                break;
            case "ew":
                $query->whereRaw($baseQuery, ['%' . addcslashes((string) $value, '%_')]);
                break;
            case "pr":
                $query->whereNotNull($this->attribute);
                break;
            case "gt":
            case "ge":
            case "lt":
            case "le":
                throw new SCIMException("This operator is not supported for this field: " . $operator);
                break;
            default:
                throw new SCIMException("Unknown operator " . $operator);
        }
    }
}
