<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use Illuminate\Contracts\Support\Arrayable;
use ArieTimmerman\Laravel\SCIMServer\Attribute\AbstractComplex;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Attribute;
use ArieTimmerman\Laravel\SCIMServer\Attribute\Collection;
use ArieTimmerman\Laravel\SCIMServer\Attribute\JSONCollection;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ComparisonExpression;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Negation;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Conjunction;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Disjunction;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path as ParserPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Factor;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ValuePath;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Filter as AstFilter;

class Helper
{
    public static function getAuthUserClass()
    {
        return config('auth.providers.users.model');
    }

    /**
     *
     * @param unknown $object
     */
    public static function prepareReturn(Arrayable $object, ResourceType $resourceType = null, array $attributes = [], array $excludedAttributes = [])
    {
        $result = null;

        if (!empty($object) && isset($object[0]) && is_object($object[0])) {
            if (!in_array('ArieTimmerman\Laravel\SCIMServer\Traits\SCIMResource', class_uses(get_class($object[0])))) {
                $result = [];

                foreach ($object as $key => $value) {
                    $result[] = self::objectToSCIMArray($value, $resourceType, $attributes, $excludedAttributes);
                }
            }
        }

        if ($result == null) {
            $result = $object;
        }

        if (is_array($result) && !empty($excludedAttributes)) {
            $defaultSchema = $resourceType?->getMapping()->getDefaultSchema();
            $result = self::applyExcludedAttributes($result, $excludedAttributes, $defaultSchema);
        }

        return $result;
    }

    public static function objectToSCIMArray($object, ResourceType $resourceType = null, array $attributes = [], array $excludedAttributes = [])
    {
        if($resourceType == null){
            $result = $object instanceof Arrayable ? $object->toArray() : $object;

            if (is_array($result) && !empty($excludedAttributes)) {
                $result = self::applyExcludedAttributes($result, $excludedAttributes);
            }

            return $result;
        }

        $mapping = $resourceType->getMapping();
        $result = $mapping->read($object, $attributes)->value;

        if (!empty($excludedAttributes)) {
            $result = self::applyExcludedAttributes($result, $excludedAttributes, $mapping->getDefaultSchema());
        }

        if (config('scim.omit_main_schema_in_return')) {
            $defaultSchema = collect($mapping->getDefaultSchema())->first();

            // Move main schema to the top. It may not be defined, for example when only specific attributes are requested.
            $main = $result[$defaultSchema] ?? [];
            
            unset($result[$defaultSchema]);

            $result = array_merge($result, $main);
        }

        return $result;
    }


    public static function getResourceObjectVersion($object)
    {
        $version = null;

        if (method_exists($object, "getSCIMVersion")) {
            $version = $object->getSCIMVersion();
        } else {
            $version = sprintf('W/"%s"', sha1($object->getKey() . $object->updated_at . $object->created_at));
        }

        // Entity tags uniquely representing the requested resources. They are a string of ASCII characters placed between double quotes
        return $version;
    }

    /**
     *
     * @param unknown      $object
     * @param ResourceType $resourceType
     */
    public static function objectToSCIMResponse(Model $object, ResourceType $resourceType = null, array $attributes = [], array $excludedAttributes = [])
    {
        $response = response(self::objectToSCIMArray($object, $resourceType, $attributes, $excludedAttributes))
            ->header('ETag', self::getResourceObjectVersion($object));

        if ($resourceType !== null) {
            $resourceTypeName = $resourceType->getName();

            if ($resourceTypeName === null) {
                $routeResourceType = request()?->route('resourceType');

                if ($routeResourceType instanceof ResourceType) {
                    $resourceTypeName = $routeResourceType->getName();
                } elseif (is_string($routeResourceType)) {
                    $resourceTypeName = $routeResourceType;
                }
            }

            if ($resourceTypeName !== null) {
                $response->header(
                    'Location',
                    route(
                        'scim.resource',
                        [
                            'resourceType' => $resourceTypeName,
                            'resourceObject' => $object->getKey(),
                        ]
                    )
                );
            }
        }

        return $response;
    }

    /**
     * See https://tools.ietf.org/html/rfc7644#section-3.4.2.2
     *
     * @throws SCIMException
     */
    public static function scimFilterToLaravelQuery(ResourceType $resourceType, Builder &$query, ParserPath $path)
    {
        $node = $path->node;
        if ($node instanceof Negation) {
            $filter = $node->getFilter();

            $query->whereNot(
                function (Builder $nested) use ($resourceType, $filter) {
                    Helper::scimFilterToLaravelQuery($resourceType, $nested, new ParserPath($filter, $filter->dump()));
                }
            );

            return;
        } elseif ($node instanceof ComparisonExpression) {
            $resourceType->getMapping()->applyComparison($query, $path);
        } elseif ($node instanceof Conjunction) {
            foreach ($node->getFactors() as $factor) {
                $query->where(
                    function ($query) use ($factor, $resourceType) {
                        Helper::scimFilterToLaravelQuery($resourceType, $query, new ParserPath($factor, $factor->dump()));
                    }
                );
            }
        } elseif ($node instanceof Disjunction) {
            foreach ($node->getTerms() as $term) {
                $query->orWhere(
                    function ($query) use ($term, $resourceType) {
                        Helper::scimFilterToLaravelQuery($resourceType, $query, new ParserPath($term, $term->dump()));
                    }
                );
            }
        } elseif ($node instanceof ValuePath) {
            $attribute = static::resolveAttributeChain($resourceType->getMapping(), $path->getValuePathAttributes());

            if ($attribute === null) {
                throw (new SCIMException('Unknown attribute referenced in valuePath filter'))->setCode(400)->setScimType('invalidFilter');
            }

            static::applyValuePathFilterToAttribute($attribute, $query, $node->getFilter());

            return;
        } elseif ($node instanceof Factor) {
            throw new SCIMException('Unknown filter not supported');
        }
    }

    protected static function applyValuePathFilterToAttribute(Attribute $attribute, Builder $query, AstFilter $filter): void
    {
        if ($attribute instanceof Collection) {
            $query->whereHas(
                $attribute->getRelationshipName(),
                function (Builder $relationQuery) use ($attribute, $filter) {
                    static::applyFilterInCollectionContext($attribute, $relationQuery, $filter);
                }
            );

            return;
        }

        if ($attribute instanceof JSONCollection) {
            throw (new SCIMException(sprintf('ValuePath filters are not supported for attribute "%s"', $attribute->getFullKey())))->setCode(501);
        }

        throw (new SCIMException(sprintf('ValuePath filters are only supported for multi-valued attributes. Attribute "%s" is not multi-valued.', $attribute->getFullKey())))->setCode(400)->setScimType('invalidFilter');
    }

    protected static function applyFilterInCollectionContext(Collection $collection, Builder $query, AstFilter $filter): void
    {
        if ($filter instanceof ComparisonExpression) {
            $path = new ParserPath($filter, $filter->dump());
            $attributeNames = $path->getAttributePathAttributes();

            if (empty($attributeNames)) {
                throw (new SCIMException('ValuePath filter must reference an attribute'))->setCode(400)->setScimType('invalidFilter');
            }

            $subAttribute = $collection->getSubNode($attributeNames[0]);

            if ($subAttribute === null) {
                throw (new SCIMException(sprintf('Unknown attribute "%s" in valuePath filter', $attributeNames[0])))->setCode(400)->setScimType('invalidFilter');
            }

            $tablePrefix = method_exists($query, 'getModel') ? $query->getModel()->getTable() : null;

            $subAttribute->applyComparison($query, $path->shiftAttributePathAttributes(), $tablePrefix);

            return;
        }

        if ($filter instanceof Conjunction) {
            foreach ($filter->getFactors() as $factor) {
                static::applyFilterInCollectionContext($collection, $query, $factor);
            }

            return;
        }

        if ($filter instanceof Disjunction) {
            $query->where(
                function (Builder $nested) use ($collection, $filter) {
                    foreach ($filter->getTerms() as $index => $term) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $nested->{$method}(
                            function (Builder $inner) use ($collection, $term) {
                                static::applyFilterInCollectionContext($collection, $inner, $term);
                            }
                        );
                    }
                }
            );

            return;
        }

        if ($filter instanceof Negation) {
            $query->whereNot(
                function (Builder $nested) use ($collection, $filter) {
                    static::applyFilterInCollectionContext($collection, $nested, $filter->getFilter());
                }
            );

            return;
        }

        if ($filter instanceof ValuePath) {
            $path = new ParserPath($filter, $filter->dump());
            $target = static::resolveAttributeChain($collection, $path->getValuePathAttributes());

            if ($target === null) {
                throw (new SCIMException('Unknown attribute referenced in nested valuePath filter'))->setCode(400)->setScimType('invalidFilter');
            }

            static::applyValuePathFilterToAttribute($target, $query, $filter->getFilter());

            return;
        }

        throw (new SCIMException('Unsupported filter found in valuePath expression'))->setCode(400)->setScimType('invalidFilter');
    }

    protected static function resolveAttributeChain(Attribute $attribute, array $segments): ?Attribute
    {
        $current = $attribute;

        foreach ($segments as $segment) {
            if (!($current instanceof AbstractComplex)) {
                return null;
            }

            $next = $current->getSubNode($segment);

            if ($next === null) {
                return null;
            }

            $current = $next;
        }

        return $current;
    }

    protected static function applyExcludedAttributes(array $resource, array $excludedAttributes, $defaultSchema = null): array
    {
        foreach ($excludedAttributes as $reference) {
            $reference = trim($reference);

            if ($reference === '') {
                continue;
            }

            [$schema, $segments] = self::splitAttributeReference($reference, $defaultSchema);

            if (empty($segments)) {
                continue;
            }

            if ($schema !== null) {
                if (!isset($resource[$schema]) || !is_array($resource[$schema])) {
                    continue;
                }

                self::removeAttributePath($resource[$schema], $segments);

                if (is_array($resource[$schema]) && empty($resource[$schema])) {
                    unset($resource[$schema]);
                }

                continue;
            }

            self::removeAttributePath($resource, $segments);
        }

        return $resource;
    }

    protected static function splitAttributeReference(string $reference, $defaultSchema = null): array
    {
        $schema = null;
        $attributePart = $reference;

        if (str_starts_with($reference, 'urn:')) {
            $lastColon = strrpos($reference, ':');

            if ($lastColon !== false) {
                $schema = substr($reference, 0, $lastColon);
                $attributePart = substr($reference, $lastColon + 1);
            }
        }

        $attributePart = trim($attributePart);

        if ($schema === null) {
            $firstSegment = $attributePart;

            if (($dotPosition = strpos($attributePart, '.')) !== false) {
                $firstSegment = substr($attributePart, 0, $dotPosition);
            }

            if (!in_array($firstSegment, ['schemas', 'meta', 'id'], true) && $defaultSchema !== null) {
                $schema = $defaultSchema;
            }
        }

        $segments = $attributePart === '' ? [] : explode('.', $attributePart);

        return [$schema, $segments];
    }

    protected static function removeAttributePath(&$node, array $segments, int $depth = 0): void
    {
        if (!is_array($node)) {
            return;
        }

        if (self::isList($node)) {
            foreach ($node as &$element) {
                self::removeAttributePath($element, $segments, $depth);
            }

            return;
        }

        $key = $segments[$depth] ?? null;

        if ($key === null || !array_key_exists($key, $node)) {
            return;
        }

        if ($depth === count($segments) - 1) {
            unset($node[$key]);
            return;
        }

        self::removeAttributePath($node[$key], $segments, $depth + 1);

        if (is_array($node[$key]) && empty($node[$key])) {
            unset($node[$key]);
        }
    }

    protected static function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        $expectedKey = 0;

        foreach ($value as $key => $unused) {
            if ($key !== $expectedKey) {
                return false;
            }

            $expectedKey++;
        }

        return true;
    }

    public static function getFlattenKey($parts, $schemas)
    {
        $result = "";

        $partsCopy = $parts;

        $first = Arr::first($partsCopy);

        if ($first != null) {
            if (in_array($first, $schemas)) {
                $result .= $first . ":";
                array_shift($partsCopy);
            } else {
                // If no schema is provided, use the first schema as its schema.
                $result .= $schemas[0] . ":";
            }

            $result .= implode(".", $partsCopy);
        } else {
            throw (new SCIMException("unknown error. " . json_encode($partsCopy)));
        }

        return $result;
    }

    /**
     *
     */
    public static function flatten($array, array $schemas, $parts = [])
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_numeric($key)) {
                $final = self::getFlattenKey($parts, $schemas);

                if (!isset($result[$final])) {
                    $result[$final] = [];
                }

                $result[$final][$key] = $value;
            } elseif (is_array($value)) {
                //Empty values do matter. For example in case of empty-ing a multi-valued attribute via PUT/replace
                if (empty($value)) {
                    $partsCopy = $parts;
                    $partsCopy[] = $key;
                    $final = self::getFlattenKey($partsCopy, $schemas);
                    $result[$final] = $value;
                } else {
                    $result = $result + self::flatten($value, $schemas, array_merge($parts, [$key]));
                }
            } else {
                $partsCopy = $parts;
                $partsCopy[] = $key;

                $result[self::getFlattenKey($partsCopy, $schemas)] = $value;
            }
        }

        return $result;
    }
}
