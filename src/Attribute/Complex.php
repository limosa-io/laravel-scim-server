<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Parser;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Complex extends AbstractComplex
{

    protected $type = 'complex';

    /**
     * @return string[]
     */
    public function getSchemas()
    {
        return collect($this->getSchemaNodes())->map(fn ($element) => $element->name)->values()->toArray();
    }


    public function read(&$object, array $attributes = []): ?AttributeValue
    {
        if (!($this instanceof Schema) && $this->parent != null && !empty($attributes) && !in_array($this->name, $attributes) && !in_array($this->getFullKey(), $attributes)) {
            return null;
        }

        $result = $this->doRead($object, $attributes);
        return !empty($result) ? new AttributeValue($result) : null;
    }

    protected function doRead(&$object, $attributes = [])
    {
        $result = [];
        foreach ($this->subAttributes as $attribute) {
            if (($r = $attribute->read($object, $attributes)) != null) {
                if (config('scim.omit_null_values') && $r->value === null) {
                    continue;
                }
                $result[$attribute->name] = $r->value;
            }
        }
        return $result;
    }

    public function patch($operation, $value, Model &$object, Path $path = null, $removeIfNotSet = false)
    {
        $this->dirty = true;

        if ($this->mutability == 'readOnly') {
            // silently ignore
            return;
        }

        if ($path != null && $path->isNotEmpty()) {
            $attributeNames = $path->getValuePathAttributes();

            if (!empty($attributeNames)) {
                // TODO: search for schema node
                $attribute = $this->getSubNode($attributeNames[0]);
                if ($attribute != null) {
                    $attribute->patch($operation, $value, $object, $path->shiftValuePathAttributes());
                } elseif ($this->parent == null) {
                    // pass the unchanged path object to the schema node
                    $this->getSchemaNode()->patch($operation, $value, $object, $path);
                } else {
                    throw new SCIMException('Unknown path: ' . (string)$path . ", in object: " . $this->getFullKey());
                }
            } elseif ($path->getValuePathFilter() != null) {
                // apply filtering here, for each match, call replace with updated path
                throw new \Exception('Filtering not implemented for this complex attribute');
            } elseif ($path->getAttributePath() != null) {
                $attributeNames = $path?->getAttributePath()?->getAttributeNames() ?? [];

                if (!empty($attributeNames)) {
                    $attribute = $this->getSubNode($attributeNames[0]);
                    if ($attribute != null) {
                        $attribute->patch($operation, $value, $object, $path->shiftAttributePathAttributes());
                    } elseif ($this->parent == null) {
                        // this is the root node, check within the first (the default) schema node
                        // pass the unchanged path object
                        $this->getSchemaNode()->patch($operation, $value, $object, $path);
                    } else {
                        throw new SCIMException('Unknown path: ' . (string)$path . ", in object: " . $this->getFullKey());
                    }
                }
            }
        } else {
            // if there is no path, keys of value are attribute names
            switch($operation) {
                case 'replace':
                    $this->replace($value, $object, $path, false);
                    break;
                case 'add':
                    $this->add($value, $object, $path);
                    break;
                case 'remove':
                    $this->remove($value, $object, $path);
                    break;
                default:
                    throw new SCIMException('Unknown operation: ' . $operation);
            }
        }
    }

    /*
        * @param $value
        * @param Model $object
    */
    public function replace($value, Model &$object, Path $path = null, $removeIfNotSet = false)
    {
        $this->dirty = true;

        if ($this->mutability == 'readOnly') {
            // silently ignore
            return;
        }

        // FIXME: figure out why this is not an iterable...
        if (! is_iterable($value)) {
          return;
        }

        // if there is no path, keys of value are attribute paths
        foreach ($value as $key => $v) {
            if (is_numeric($key)) {
                throw new SCIMException('Invalid key: ' . $key . ' for complex object ' . $this->getFullKey());
            }

            $subNode = null;

            // if path contains : it is a schema node
            if (str_contains($key, ':')) {
                $subNode = $this->getSubNode($key);
            } else {
                $path = Parser::parse($key);

                if ($path?->isNotEmpty()) {
                    $attributeNames = $path->getAttributePathAttributes();
                    $path = $path->shiftAttributePathAttributes();
                    $sub = $attributeNames[0] ?? $path->getAttributePath()?->path?->schema;
                    $subNode = $this->getSubNode($attributeNames[0] ?? $path->getAttributePath()?->path?->schema);
                }
            }

            if ($subNode != null) {
                $newValue = $v;
                if ($path?->isNotEmpty()) {
                    $newValue = [
                        implode('.', $path->getAttributePathAttributes()) => $v
                    ];
                }

                $subNode->replace($newValue, $object, $path);
            }
        }

        // if this is the root, we may also check the schema nodes
        if ($subNode == null && $this->parent == null) {
            foreach ($this->subAttributes as $attribute) {
                if ($attribute instanceof Schema) {
                    $attribute->replace($value, $object, $path);
                }
            }
        }

        if ($removeIfNotSet) {
            foreach ($this->subAttributes as $attribute) {
                if (!$attribute->isDirty()) {
                    $attribute->remove(null, $object);
                }
            }
        }
    }

    public function add($value, Model &$object)
    {
        $match = false;
        $this->dirty = true;

        if ($this->mutability == 'readOnly') {
            // silently ignore
            return;
        }

        // keys of value are attribute names
        foreach ($value as $key => $v) {
            if (is_numeric($key)) {
                throw new SCIMException('Invalid key: ' . $key . ' for complex object ' . $this->getFullKey());
            }

            $path = Parser::parse($key);

            if ($path->isNotEmpty()) {
                $attributeNames = $path->getAttributePathAttributes();
                $path = $path->shiftAttributePathAttributes();
                $subNode = $this->getSubNode($attributeNames[0]);
                $match = true;

                $newValue = $v;
                if ($path->isNotEmpty()) {
                    $newValue = [
                        implode('.', $path->getAttributePathAttributes()) => $v
                    ];
                }

                $subNode->add($newValue, $object);
            }
        }

        // if this is the root, we may also check the schema nodes
        if (!$match && $this->parent == null) {
            foreach ($this->subAttributes as $attribute) {
                if ($attribute instanceof Schema) {
                    $attribute->add($value, $object);
                }
            }
        }
    }


    public function remove($value, Model &$object, Path $path = null)
    {
        if ($this->mutability == 'readOnly') {
            // silently ignore
            return;
        }
        // TODO: implement
    }

    public function getSortAttributeByPath(Path $path)
    {
        if ($path->getValuePath() != null) {
            throw new SCIMException('Incorrect sortBy parameter');
        }

        $attributeNames = $path->getAttributePathAttributes();

        if (empty($attributeNames)) {
            throw new SCIMException('Incorrect sortBy parameter. No attributes.');
        }

        $result = null;

        // TODO: search for schema node
        $attribute = $this->getSubNode($attributeNames[0]);
        if ($attribute != null) {
            $result = $attribute->getSortAttributeByPath($path->shiftAttributePathAttributes());
        } elseif ($this->parent == null) {
            // pass the unchanged path object to the schema node
            $result = $this->getSchemaNode()->getSortAttributeByPath($path);
        } else {
            throw new SCIMException('Unknown path: ' . (string)$path . ", in object: " . $this->getFullKey());
        }

        return $result;
    }

    public function applyComparison(Builder &$query, Path $path, $parentAttribute = null)
    {
        if ($path != null && $path->isNotEmpty()) {
            $attributeNames = $path->getValuePathAttributes();

            if (!empty($attributeNames)) {
                // TODO: search for schema node
                $attribute = $this->getSubNode($attributeNames[0]);
                if ($attribute != null) {
                    // ($operation, $value, $object, $path->shiftValuePathAttributes());
                    $attribute->applyComparison($query, $path->shiftValuePathAttributes());
                } elseif ($this->parent == null) {
                    // pass the unchanged path object to the schema node
                    $this->getSchemaNode()->applyComparison($query, $path);
                } else {
                    throw new SCIMException('Unknown path: ' . (string)$path . ", in object: " . $this->getFullKey());
                }
            } elseif ($path->getValuePathFilter() != null) {
                // apply filtering here, for each match, call replace with updated path
                throw new \Exception('Filtering not implemented for this complex attribute');
            } elseif ($path->getAttributePath() != null) {
                $attributeNames = $path?->getAttributePath()?->getAttributeNames() ?? [];

                if (!empty($attributeNames)) {
                    $attribute = $this->getSubNode($attributeNames[0]);
                    if ($attribute != null) {
                        $attribute->applyComparison($query, $path->shiftAttributePathAttributes());
                    } elseif ($this->parent == null) {
                        // this is the root node, check within the first (the default) schema node
                        // pass the unchanged path object
                        $this->getSchemaNode()->applyComparison($query, $path);
                    } else {
                        throw new SCIMException('Unknown path: ' . (string)$path . ", in object: " . $this->getFullKey());
                    }
                }
            }
        }
    }

    /**
     * Return the default (core) schema. Assume it is the first one.
     * TODO: This method is only relevant for the top-level complex attribute.
     * @return string
     */
    public function getDefaultSchema()
    {
        return collect($this->subAttributes)->first(fn ($element) => $element instanceof Schema)->name;
    }
}
