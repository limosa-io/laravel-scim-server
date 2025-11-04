<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ComparisonExpression;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Conjunction;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Disjunction;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Filter as AstFilter;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Negation;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ValuePath as AstValuePath;
use ArieTimmerman\Laravel\SCIMServer\Parser\Filter as ParserFilter;
use ArieTimmerman\Laravel\SCIMServer\Parser\Parser;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Contracts\Support\Arrayable;
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
        return collect($this->getSchemaNodes())->map(fn($element) => $element->name)->values()->toArray();
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
                if (!$this->getMultiValued()) {
                    throw (new SCIMException(sprintf('ValuePath filters are only supported on multi-valued attributes. Attribute "%s" is not multi-valued.', $this->getFullKey())))->setCode(400)->setScimType('invalidFilter');
                }

                $filterWrapper = $path->getValuePathFilter();
                $filterNode = $filterWrapper instanceof ParserFilter ? $filterWrapper->filter : null;

                if (!$filterNode instanceof AstFilter) {
                    return;
                }

                $currentRaw = $this->doRead($object);
                $currentValues = $this->normalizeMultiValuedItems($currentRaw);
                if (empty($currentValues)) {
                    return;
                }

                $matchedIndexes = [];
                foreach ($currentValues as $index => $item) {
                    if ($this->matchesFilter($filterNode, $item)) {
                        $matchedIndexes[] = $index;
                    }
                }

                $attributeNames = $path?->getAttributePath()?->getAttributeNames() ?? [];
                $modified = false;

                if (empty($matchedIndexes)) {
                    if ($operation === 'add') {
                        $newElement = $this->createElementFromFilter($filterNode, $attributeNames, $value);

                        if ($newElement !== null) {
                            $currentValues[] = $newElement;
                            $modified = true;
                        }
                    }

                    if (!$modified) {
                        return;
                    }
                }

                foreach ($matchedIndexes as $index) {
                    if (empty($attributeNames)) {
                        if ($operation === 'remove') {
                            unset($currentValues[$index]);
                            $modified = true;
                            continue;
                        }

                        $valuePayload = $this->normalizeElement($value);

                        if ($operation === 'add') {
                            $currentValues[$index] = array_merge($currentValues[$index], $valuePayload);
                        } elseif ($operation === 'replace') {
                            $currentValues[$index] = $valuePayload;
                        } else {
                            throw new SCIMException('Unsupported operation: ' . $operation);
                        }

                        $modified = true;
                        continue;
                    }

                    $updated = $this->applyAttributeOperation($currentValues[$index], $attributeNames, $operation, $value);

                    if ($updated !== $currentValues[$index]) {
                        $currentValues[$index] = $updated;
                        $modified = true;
                    }
                }

                if ($modified) {
                    $normalized = array_values($currentValues);

                    // Attempt to preserve original representation when no normalization occurred.
                    if (is_array($currentRaw) && $this->isAssoc($currentRaw) === $this->isAssoc($normalized)) {
                        $normalized = $this->restoreStructure($currentRaw, $normalized);
                    }

                    $this->writeMultiValuedItems($object, $normalized);
                }
            } elseif ($path->getAttributePath() != null) {
                $attributeNames = $path?->getAttributePath()?->getAttributeNames() ?? [];

                if (!empty($attributeNames)) {
                    $schema = $path->getAttributePath()?->path?->schema;
                    // Resolve attribute either directly or via schema parent when specified
                    $attribute = $schema
                        ? ((($parent = $this->getSubNode($schema)) instanceof Schema) ? $parent->getSubNode($attributeNames[0]) : null)
                        : $this->getSubNode($attributeNames[0]);

                    if ($attribute != null) {
                        $attribute->patch($operation, $value, $object, $path->shiftAttributePathAttributes());
                        return; // done
                    }

                    if ($this->parent == null) {
                        // root node: delegate unchanged path to default schema node
                        $this->getSchemaNode()->patch($operation, $value, $object, $path);
                        return; // done
                    }

                    throw new SCIMException('Unknown path: ' . (string)$path . ", in object: " . $this->getFullKey());
                }
            }
        } else {
            // if there is no path, keys of value are attribute names
            switch ($operation) {
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

        // if value is empty, nothing to do
        if (empty($value)) {
            return;
        }

        // if there is no path, keys of value are attribute paths
        foreach ($value as $key => $v) {
            if (is_numeric($key)) {
                throw new SCIMException('Invalid key: ' . $key . ' for complex object ' . $this->getFullKey());
            }

            $subNode = null;

            // if path contains : it is a schema node
            if (strpos($key, ':') !== false) {
                $subNode = $this->getSubNode($key);
            } else {
                $path = Parser::parse($key);

                if ($path->isNotEmpty()) {
                    $attributeNames = $path->getAttributePathAttributes();
                    $path = $path->shiftAttributePathAttributes();
                    $sub = $attributeNames[0] ?? $path->getAttributePath()?->path?->schema;
                    $subNode = $this->getSubNode($attributeNames[0] ?? $path->getAttributePath()?->path?->schema);
                }
            }

            if ($subNode != null) {
                $newValue = $v;
                if ($path->isNotEmpty()) {
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
                $schemaIdentifier = $path->getValuePath()?->getAttributePath()?->path?->schema ?? null;

                if ($schemaIdentifier !== null && $this->parent === null) {
                    $schemaNode = $this->getSubNode($schemaIdentifier);

                    if ($schemaNode instanceof Schema) {
                        $schemaNode->applyComparison($query, $path);

                        return;
                    }

                    throw new SCIMException('Unknown path: ' . (string)$path . ", in object: " . $this->getFullKey());
                }

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
                    $schemaIdentifier = $path->getAttributePath()?->path?->schema ?? null;

                    if ($schemaIdentifier !== null && $this->parent === null) {
                        $schemaNode = $this->getSubNode($schemaIdentifier);

                        if ($schemaNode instanceof Schema) {
                            $schemaNode->applyComparison($query, $path);

                            return;
                        }

                        throw new SCIMException('Unknown path: ' . (string)$path . ", in object: " . $this->getFullKey());
                    }

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
        return collect($this->subAttributes)->first(fn($element) => $element instanceof Schema)->name;
    }

    private function normalizeMultiValuedItems(mixed $items): array
    {
        if ($items === null) {
            return [];
        }

        if (!is_array($items)) {
            $items = [$items];
        }

        return array_values(array_map(fn($item) => $this->normalizeElement($item), $items));
    }

    private function normalizeElement(mixed $element): array
    {
        if ($element instanceof Arrayable) {
            return $element->toArray();
        }

        if ($element instanceof \JsonSerializable) {
            $serialized = $element->jsonSerialize();
            return is_array($serialized) ? $serialized : ['value' => $serialized];
        }

        if (is_object($element)) {
            $objectVars = get_object_vars($element);
            return !empty($objectVars) ? $objectVars : ['value' => (string)$element];
        }

        if (is_array($element)) {
            return $element;
        }

        return ['value' => $element];
    }

    private function matchesFilter(AstFilter $filter, array $item): bool
    {
        if ($filter instanceof ComparisonExpression) {
            $attributeNames = $filter->attributePath->getAttributeNames();
            $actual = $this->extractValue($item, $attributeNames);
            return $this->compare($actual, $filter->operator, $filter->compareValue);
        }

        if ($filter instanceof Conjunction) {
            foreach ($filter->getFactors() as $factor) {
                if (!$this->matchesFilter($factor, $item)) {
                    return false;
                }
            }

            return true;
        }

        if ($filter instanceof Disjunction) {
            foreach ($filter->getTerms() as $term) {
                if ($this->matchesFilter($term, $item)) {
                    return true;
                }
            }

            return false;
        }

        if ($filter instanceof Negation) {
            return !$this->matchesFilter($filter->getFilter(), $item);
        }

        if ($filter instanceof AstValuePath) {
            $nestedValues = $this->extractValue($item, $filter->getAttributePath()->getAttributeNames());
            $normalized = $this->normalizeNested($nestedValues);

            foreach ($normalized as $nested) {
                if ($this->matchesFilter($filter->getFilter(), $nested)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function normalizeNested(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                return [$this->normalizeElement($value)];
            }

            return array_map(fn($item) => $this->normalizeElement($item), $value);
        }

        return [$this->normalizeElement($value)];
    }

    private function extractValue(array $item, array $attributeNames): mixed
    {
        $current = $item;

        foreach ($attributeNames as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        $operator = strtolower($operator);

        switch ($operator) {
            case 'eq':
                return $this->normalizeComparable($actual) == $this->normalizeComparable($expected);
            case 'ne':
                return $this->normalizeComparable($actual) != $this->normalizeComparable($expected);
            case 'co':
                $actualString = (string)$this->normalizeComparable($actual);
                $expectedString = (string)$this->normalizeComparable($expected);
                return $actualString !== '' && $expectedString !== '' && str_contains($actualString, $expectedString);
            case 'sw':
                $actualString = (string)$this->normalizeComparable($actual);
                $expectedString = (string)$this->normalizeComparable($expected);
                return $actualString !== '' && $expectedString !== '' && str_starts_with($actualString, $expectedString);
            case 'ew':
                $actualString = (string)$this->normalizeComparable($actual);
                $expectedString = (string)$this->normalizeComparable($expected);
                return $actualString !== '' && $expectedString !== '' && str_ends_with($actualString, $expectedString);
            case 'gt':
                return $this->normalizeComparable($actual) > $this->normalizeComparable($expected);
            case 'ge':
                return $this->normalizeComparable($actual) >= $this->normalizeComparable($expected);
            case 'lt':
                return $this->normalizeComparable($actual) < $this->normalizeComparable($expected);
            case 'le':
                return $this->normalizeComparable($actual) <= $this->normalizeComparable($expected);
            case 'pr':
                $value = $this->normalizeComparable($actual);
                return $value !== null && $value !== '';
            default:
                throw new SCIMException('Unsupported filter operator ' . $operator);
        }
    }

    private function normalizeComparable(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        if (is_array($value)) {
            return null;
        }

        return (string)$value;
    }

    private function applyAttributeOperation(array $item, array $attributeNames, string $operation, mixed $value): array
    {
        $operation = strtolower($operation);
        $segment = array_shift($attributeNames);

        if ($segment === null) {
            return $item;
        }

        if (empty($attributeNames)) {
            if ($operation === 'remove') {
                unset($item[$segment]);
                return $item;
            }

            if ($operation === 'add' && array_key_exists($segment, $item) && is_array($item[$segment]) && is_array($value)) {
                $item[$segment] = array_merge($item[$segment], $value);
                return $item;
            }

            if (in_array($operation, ['add', 'replace'], true)) {
                $item[$segment] = $value;
                return $item;
            }

            throw new SCIMException('Unsupported operation: ' . $operation);
        }

        $child = $item[$segment] ?? [];
        if (!is_array($child)) {
            $child = $this->normalizeElement($child);
        }

        $item[$segment] = $this->applyAttributeOperation($child, $attributeNames, $operation, $value);

        return $item;
    }

    private function isAssoc(array $array): bool
    {
        return array_values($array) !== $array;
    }

    private function writeMultiValuedItems(Model &$object, array $items): void
    {
        $reflection = new \ReflectionMethod($this, 'replace');

        if ($reflection->getDeclaringClass()->getName() !== self::class) {
            $this->replace($items, $object, null, false);
            return;
        }

        $object->{$this->name} = $items;
        $this->dirty = true;
    }

    private function restoreStructure(array $original, array $normalized): array
    {
        if (!$this->isAssoc($original)) {
            return $normalized;
        }

        $keys = array_keys($original);
        $result = [];

        foreach ($normalized as $index => $value) {
            $key = $keys[$index] ?? $index;
            $result[$key] = $value;
        }

        return $result;
    }

    private function createElementFromFilter(AstFilter $filter, array $attributePath, mixed $value): ?array
    {
        $base = $this->extractAssignmentsFromFilter($filter);

        if (!empty($attributePath)) {
            $base = $this->setNestedValue($base, $attributePath, $value);
        } elseif (is_array($value)) {
            $base = array_replace_recursive($base, $this->normalizeElement($value));
        } else {
            return null;
        }

        return $this->normalizeElement($base);
    }

    private function extractAssignmentsFromFilter(AstFilter $filter): array
    {
        if ($filter instanceof ComparisonExpression) {
            if (strtolower($filter->operator) !== 'eq') {
                return [];
            }

            $attributeNames = $filter->attributePath->getAttributeNames();

            if (empty($attributeNames)) {
                return [];
            }

            return $this->setNestedValue([], $attributeNames, $filter->compareValue);
        }

        if ($filter instanceof Conjunction) {
            $result = [];

            foreach ($filter->getFactors() as $factor) {
                $result = array_replace_recursive($result, $this->extractAssignmentsFromFilter($factor));
            }

            return $result;
        }

        if ($filter instanceof AstValuePath) {
            $nested = $this->extractAssignmentsFromFilter($filter->getFilter());

            $attributeNames = $filter->getAttributePath()->getAttributeNames();

            return $this->setNestedValue([], $attributeNames, $nested);
        }

        return [];
    }

    private function setNestedValue(array $array, array $path, mixed $value): array
    {
        if (empty($path)) {
            return is_array($value) ? array_replace_recursive($array, $value) : $array;
        }

        $segment = array_shift($path);

        if (!array_key_exists($segment, $array) || !is_array($array[$segment])) {
            $array[$segment] = [];
        }

        if (empty($path)) {
            $array[$segment] = $value;
            return $array;
        }

        $array[$segment] = $this->setNestedValue($array[$segment], $path, $value);

        return $array;
    }
}
