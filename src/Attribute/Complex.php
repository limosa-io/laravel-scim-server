<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Complex extends AbstractComplex
{
    
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
            if(($r = $attribute->read($object, $attributes)) != null){
                $result[$attribute->name] = $r->value;
            }
        }
        return $result;
    }

    public function patch($operation, $value, Model &$object, Path $path = null, $removeIfNotSet = false)
    {
        $this->dirty = true;

        if($this->mutability == 'readOnly'){
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
        $match = false;
        $this->dirty = true;

        if($this->mutability == 'readOnly'){
            // silently ignore
            return;
        }

        // if there is no path, keys of value are attribute names
        foreach ($value as $key => $v) {
            if (is_numeric($key)) {
                throw new SCIMException('Invalid key: ' . $key . ' for complex object ' . $this->getFullKey());
            }

            $attribute = $this->getSubNode($key);
            if ($attribute != null) {
                $attribute->replace($v, $object, $path);
                $match = true;
            }
        }

        // if this is the root, we may also check the schema nodes
        if (!$match && $this->parent == null) {
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


    public function remove($value, Model &$object, string $path = null)
    {
        if($this->mutability == 'readOnly'){
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

    public function applyComparison(Builder &$query, Path $path, $parentAttribute = null){
        
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
}
