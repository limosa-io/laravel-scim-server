<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use Illuminate\Database\Eloquent\Model;
use Tmilos\ScimFilterParser\Ast\AttributePath;
use Tmilos\ScimFilterParser\Ast\ComparisonExpression;
use Tmilos\ScimSchema\Model\Resource;

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

    public $schemaNode = false;
    public $validations = [];
    public $filter;
    public $sortAttribute;

    public $dirty = false;



    public function __construct($name = null, $schemaNode = false)
    {
        $this->name = $name;
        $this->schemaNode = $schemaNode;
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

    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    public function read(&$object)
    {
        throw new SCIMException(sprintf('Read is not implemented for "%s"', $this->getFullKey()));
    }

    public function getFullKey()
    {
        if ($this->parent != null && $this->parent->name != null) {
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

    public function getNode(?AttributePath $attributePath)
    {
        if (empty($attributePath)) {
            return $this;
        }

        //The first schema should be the default one
        $schema = $attributePath->schema ?? $this->getDefaultSchema();

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
        throw new SCIMException(sprintf('Filtering is not implemented for "%s"', $this->getFullKey()));
    }

    public function add($value, Model &$object)
    {
        new SCIMException(sprintf('Write is not implemented for "%s"', $this->getFullKey()));
    }

    public function replace($value, Model &$object, Path $path = null)
    {
        throw new SCIMException(sprintf('Replace is not implemented for "%s"', $this->getFullKey()));
    }

    public function patch($operation, $value, Model &$object, Path $path = null)
    {
        throw new SCIMException(sprintf('Patch is not implemented for "%s"', $this->getFullKey()));
    }

    public function remove($value, Model &$object, string $path = null)
    {
        throw new SCIMException(sprintf('Remove is not implemented for "%s"', $this->getFullKey()));
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

    public function getSortAttributeByPath(Path $path)
    {
        throw new SCIMException(sprintf('Sort is not implemented for "%s"', $this->getFullKey()));
    }

    public function isDirty()
    {
        return $this->dirty;
    }
}
