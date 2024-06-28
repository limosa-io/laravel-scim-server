<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use Tmilos\ScimFilterParser\Ast\AttributePath;
use Tmilos\ScimFilterParser\Ast\ComparisonExpression;

class Attribute
{
    protected $name = null;

    public $read;
    public $add;

    public $replace;

    public $remove;

    public $writeAfter;
    private $readEnabled = true;
    private $writeEnabled = true;

    /** @var Attribute */
    public $parent = null;

    public $eloquentAttributes = [];
    public $relationship;
    public $schemaNode = false;
    public $validations = [];
    public $filter;
    public $sortAttribute;

    /**
     * @var Attribute[]
     */
    public $subAttributes = [];

    public function __construct($name = null, $schemaNode = false)
    {
        $this->name = $name;
        $this->schemaNode = $schemaNode;
    }

    public function getSubNode($key)
    {
        return collect($this->subAttributes)->first(function ($element) use ($key) {
            return $element->name == $key;
        });
    }

    public function ensure(...$validations)
    {
        $this->validations = $validations;

        return $this;
    }

    public function isRequired()
    {
        return in_array('required', $this->validations);
    }

    public function nullable()
    {
        return in_array('nullable', $this->validations);
    }

    public function isReadSupported()
    {
        return $this->readEnabled;
    }

    public function isWriteSupported()
    {
        return $this->writeEnabled;
    }

    public function shouldReturn(&$object)
    {
        return true;
    }

    public function eloquent(string $attribute = null)
    {
        if ($attribute == null) {
            $attribute = $this->name;
        }

        $this->eloquentAttributes = [$attribute];

        $this->setRead(function ($object) use ($attribute) {

            $value = $object->{$attribute};

            if ($value instanceof \Carbon\Carbon) {
                $value = $value->format('c');
            }

            return $value;
        })->setAdd(
            function ($value, &$object) use ($attribute) {
                $object->{$attribute} = $value;
            }
        )->setReplace(
            function ($value, &$object) use ($attribute) {
                $object->{$attribute} = $value;
            }
        )->setSortAttribute($attribute);

        return $this;
    }

    public function constant($text)
    {
        return $this->disableWrite()->setRead(
            function (&$object) use ($text) {
                return $text;
            }
        );
    }

    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    public function object(...$attributes)
    {
        $this->subAttributes = $attributes;

        foreach ($attributes as $attribute) {
            $attribute->setParent($this);
        }

        $this->setRead(function (&$object) {
            $result = [];
            foreach ($this->subAttributes as $attribute) {
                $result[$attribute->name] = $attribute->read($object);
            }
            return $result;
        });

        return $this;
    }

    public function collection(...$attributes)
    {
        $this->subAttributes = $attributes;

        foreach ($attributes as $attribute) {
            $attribute->setParent($this);
        }

        $this->setRead(function (&$object) {
            $result = [];
            foreach ($this->subAttributes as $attribute) {
                $result[] = $attribute->read($object);
            }
            return $result;
        });

        return $this;
    }

    public function read(&$object)
    {
        if ($this->read) {
            return ($this->read)($object);
        } else {
            throw new SCIMException(sprintf('Read is not implemented for "%s"', $this->getFullKey()));
        };
    }

    public function setRead($read)
    {
        $this->read = $read;
        return $this;
    }

    public function disableWrite(): Attribute
    {
        $this->add = $this->replace = $this->remove = function ($value, &$object) {
            throw (new SCIMException(sprintf('Write to "%s" is not supported', $this->getFullKey())))->setCode(500)->setScimType('mutability');
        };

        $this->writeEnabled = false;

        return $this;
    }

    public function ignoreWrite()
    {
        $this->add = $this->replace = $this->remove = function ($value, &$object) {
            //ignore
        };

        return $this;
    }

    public function disableRead()
    {
        $this->read = function (&$object) {
            // throw new SCIMException('Read is not supported for ' . $parent->getFullKey());
            return null; //"disabled!!";
        };

        $this->readEnabled = false;

        return $this;
    }

    public function setRelationship($relationship)
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function getFullKey()
    {
        if ($this->parent != null) {
            $seperator = $this->parent->schemaNode ? ':' : '.';
            return $this->parent->getFullKey() . $seperator . $this->name;
        } else {
            return $this->name;
        }
    }

    public function getDefaultSchema()
    {
        // TODO: why this?
        return Schema::SCHEMA_USER;
    }

    public function getSchema()
    {
        if ($this->schemaNode) {
            return $this->name;
        } else {
            return $this->parent?->getSchema();
        }
    }

    // TODO: only invoked on the highest level???
    public function getNode(?AttributePath $attributePath)
    {
        if (empty($attributePath)) {
            return $this;
        }

        //The first schema should be the default one
        $schema = $attributePath->schema ?? $this->getDefaultSchema()[0];

        if (!empty($schema) && !empty($this->getSchema()) && $this->getSchema() != $schema) {
            throw (new SCIMException(sprintf('Trying to get attribute for schema "%s". But schema is already "%s"', $attributePath->schema, $this->getSchema())))->setCode(500)->setScimType('noTarget');
        }

        $elements = [];

        // The attribute mapping MUST include the schema. Therefore, add the schema to the first element.
        if (empty($attributePath->attributeNames) && !empty($schema)) {
            $elements[] = $schema;
        } elseif (empty($this->getSchema()) && !in_array($attributePath->attributeNames[0], Schema::ATTRIBUTES_CORE)) {
            $elements[] = $schema ?? (is_array($this->getDefaultSchema()) ? $this->getDefaultSchema()[0] : $this->getDefaultSchema());
        }

        foreach ($attributePath->attributeNames as $a) {
            $elements[] = $a;
        }

        /**
         * @var Attribute $node
        */
        $node = $this;

        foreach ($elements as $element) {
            try {
                $node = $node->getSubNode($element, $schema);

                if ($node instanceof Attribute && $this->getDefaultSchema()) {
                    //$node->setDefaultSchema($this->getDefaultSchema());
                }
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $node;
    }

    /**
     * Used by scimFilterToLaravelQuery and getEloquentSortAttribute
     * Example filter:
     * - urn:ietf:params:scim:schemas:core:2.0:User:userName eq "bjensen"
     * - userName co "jensen"
     * example sort:
     * urn:ietf:params:scim:schemas:core:2.0:User:userName
     */
    public function getSubNodeWithPath($path)
    {
        if ($path == null) {
            return $this;
        } else {
            $getAttributePath = function () {
                return $this->attributePath;
            };

            $getValuePath = function () {
                return $this->valuePath;
            };

            $getFilter = function () {
                return $this->filter;
            };

            $first = @$getAttributePath->call((object)$getValuePath->call($path));
            $filter = @$getFilter->call((object)$getValuePath->call($path));
            $last = $getAttributePath->call((object)$path);

            return $this->getNode($first)->withFilter($filter)->getNode($last);
        }
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

    public function withFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

    public function getEloquentAttributes()
    {
        return $this->eloquentAttributes;
    }

    public function applyWhereConditionDirect($attribute, &$query, $operator, $value)
    {
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

    public function applyWhereCondition(&$query, $operator, $value)
    {

        //only filter on OWN eloquent attributes
        if (empty($this->eloquentAttributes)) {
            throw new SCIMException("Can't filter on . " . $this->getFullKey());
        }

        $attribute = $this->eloquentAttributes[0];

        if ($this->relationship != null) {
            $query->whereHas(
                $this->relationship,
                function ($query) use ($attribute, $operator, $value) {
                    $this->applyWhereConditionDirect($attribute, $query, $operator, $value);
                }
            )->get();
        } else {
            $this->applyWhereConditionDirect($attribute, $query, $operator, $value);
        }
    }

    public function writeAfterIgnore($value, &$object)
    {
    }

    public function add($value, &$object)
    {
        return $this->add ? ($this->add)($value, $object) : new SCIMException(sprintf('Write is not implemented for "%s"', $this->getFullKey()));
    }

    public function replace($value, &$object)
    {
        $current = $this->read($object);

        //TODO: Really implement replace ...???
        return $this->replace ? ($this->replace)($value, $object) : throw new SCIMException(sprintf('Replace is not implemented for "%s"', $this->getFullKey()));
    }

    public function remove($value, &$object)
    {

        //TODO: implement remove for multi valued attributes
        return $this->remove ? ($this->remove)($value, $object) : null;
    }

    public function writeAfter($value, &$object)
    {
        return $this->writeAfter ? ($this->writeAfter)($value, $object) : $this->writeAfterIgnore($value, $object);
    }

    public function setAdd($write)
    {
        $this->add = $write;

        return $this;
    }

    public function setRemove($write)
    {
        $this->remove = $write;

        return $this;
    }

    public function setReplace($replace)
    {
        $this->replace = $replace;

        return $this;
    }

    public function setSortAttribute($attribute)
    {
        $this->sortAttribute = $attribute;

        return $this;
    }

    public function getSortAttribute()
    {
        if (!$this->readEnabled) {
            throw new SCIMException(sprintf('Can\'t sort on unreadable attribute "%s"', $this->getFullKey()));
        }

        return $this->sortAttribute;
    }
}
