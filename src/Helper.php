<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\SCIM\ShowResponse;
use Illuminate\Contracts\Support\Arrayable;
use Tmilos\ScimFilterParser\Ast\ComparisonExpression;
use Tmilos\ScimFilterParser\Ast\Negation;
use Tmilos\ScimFilterParser\Ast\Conjunction;
use Tmilos\ScimFilterParser\Ast\Disjunction;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path as ParserPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Tmilos\ScimFilterParser\Ast\Factor;
use Tmilos\ScimFilterParser\Ast\ValuePath;

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
    public static function prepareReturn(Arrayable $object, ?ResourceType $resourceType = null, array $attributes = [], array $excludedAttributes = [])
    {
        $result = null;

        if (isset($object[0]) && is_object($object[0])) {
            if (! in_array('ArieTimmerman\Laravel\SCIMServer\Traits\SCIMResource', class_uses($object[0]::class))) {
                $result = [];

                foreach ($object as $key => $value) {
                    $result[] = self::objectToSCIMArray($value, $resourceType, $attributes, $excludedAttributes);
                }
            }
        }

        if ($result == null) {
            $result = $object;
        }

        return $result;
    }

    public static function objectToSCIMArray($object, ?ResourceType $resourceType = null, array $attributes = [], array $excludedAttributes = [])
    {
        if($resourceType == null){
            return $object instanceof Arrayable ? $object->toArray() : $object;
        }

        $mapping = $resourceType->getMapping();
        $result = $mapping->read($object, $attributes)->value;

        if (config('scim.omit_main_schema_in_return')) {
            $defaultSchema = collect($mapping->getDefaultSchema())->first();

            // Move main schema to the top. It may not be defined, for example when only specific attributes are requested.
            $main = $result[$defaultSchema] ?? [];
            
            unset($result[$defaultSchema]);

            $result = array_merge($result, $main);
        }

        return Arr::except($result, $excludedAttributes);
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
    public static function objectToSCIMResponse(Model $object, ?ResourceType $resourceType = null, array $excludedAttributes = [])
    {
        $response = self::objectToSCIMArray($object, $resourceType, excludedAttributes: $excludedAttributes);

        return response($response)->header('ETag', self::getResourceObjectVersion($object));
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

            throw new SCIMException('Negation filters not supported')->setCode(400)->setScimType('invalidFilter');
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
            throw new SCIMException('ValuePath not supported');
        } elseif ($node instanceof Factor) {
            throw new SCIMException('Unknown filter not supported');
        }
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
