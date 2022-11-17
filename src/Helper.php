<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;
use Illuminate\Contracts\Support\Arrayable;
use Tmilos\ScimFilterParser\Ast\ComparisonExpression;
use Tmilos\ScimFilterParser\Ast\Negation;
use Tmilos\ScimFilterParser\Ast\Conjunction;
use Tmilos\ScimFilterParser\Ast\Disjunction;
use Tmilos\ScimFilterParser\Parser;
use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Ast\Path;
use Tmilos\ScimFilterParser\Ast\AttributePath;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
    public static function prepareReturn(Arrayable $object, ResourceType $resourceType = null)
    {
        $result = null;

        if (!empty($object) && isset($object[0]) && is_object($object[0])) {
            if (!in_array('ArieTimmerman\Laravel\SCIMServer\Traits\SCIMResource', class_uses(get_class($object[0])))) {
                $result = [];

                foreach ($object as $key => $value) {
                    $result[] = self::objectToSCIMArray($value, $resourceType);
                }
            }
        }

        if ($result == null) {
            $result = $object;
        }

        return $result;
    }

    // TODO: Auto map eloquent attributes with scim naming to the correct attributes
    public static function objectToSCIMArray($object, ResourceType $resourceType = null)
    {
        $userArray = $object->toArray();

        // If the getDates-method exists, ensure proper formatting of date attributes
        if (method_exists($object, 'getDates')) {
            $dateAttributes = $object->getDates();
            foreach ($dateAttributes as $dateAttribute) {
                if (isset($userArray[$dateAttribute])) {
                    $userArray[$dateAttribute] = $object->getAttribute($dateAttribute)->format('c');
                }
            }
        }

        $result = [];

        if ($resourceType != null) {
            $mapping = $resourceType->getMapping();

            $uses = $mapping->getEloquentAttributes();

            $result = $mapping->read($object);

            foreach ($uses as $key) {
                unset($userArray[$key]);
            }

            if (!empty($userArray) && (($resourceType->getConfiguration()['map_unmapped']) ?? false)) {
                $namespace = $resourceType->getConfiguration()['unmapped_namespace'] ?? null;

                $parent = null;

                if ($namespace != null) {
                    if (!isset($result[$namespace])) {
                        $result[$namespace] = [];
                    }
                    $parent = &$result[$namespace];
                } else {
                    $parent = &$result;
                }

                foreach ($userArray as $key => $value) {
                    $parent[$key] = AttributeMapping::eloquentAttributeToString($value);
                }
            }

            if (config('scim.omit_main_schema_in_return')) {
                $defaultSchema = collect($mapping->getDefaultSchema())->first();

                $main = $result[$defaultSchema];
                unset($result[$defaultSchema]);

                $result = array_merge($result, $main);
            }
            
        } else {
            $result = $userArray;
        }

        return $result;
    }


    public static function getResourceObjectVersion($object)
    {
        $version = null;

        if (method_exists($object, "getSCIMVersion")) {
            $version = $object->getSCIMVersion();
        } else {
            $version = sha1($object->getKey() . $object->updated_at . $object->created_at);
        }

        // Entity tags uniquely representing the requested resources. They are a string of ASCII characters placed between double quotes
        return sprintf('W/"%s"', $version);
    }

    /**
     *
     * @param unknown      $object
     * @param ResourceType $resourceType
     */
    public static function objectToSCIMResponse(Model $object, ResourceType $resourceType = null)
    {
        return response(self::objectToSCIMArray($object, $resourceType))->setEtag(self::getResourceObjectVersion($object));
    }

    public static function objectToSCIMCreateResponse(Model $object, ResourceType $resourceType = null)
    {
        return self::objectToSCIMResponse($object, $resourceType)->setStatusCode(201);
    }

    /**
     * See https://tools.ietf.org/html/rfc7644#section-3.4.2.2
     *
     * @param  unknown $query
     * @param  unknown $node
     * @throws SCIMException
     */
    public static function scimFilterToLaravelQuery(ResourceType $resourceType, &$query, $node)
    {

        //var_dump($node);exit;

        if ($node instanceof Negation) {
            $filter = $node->getFilter();

            throw (new SCIMException('Negation filters not supported'))->setCode(400)->setScimType('invalidFilter');
        } elseif ($node instanceof ComparisonExpression) {
            $operator = strtolower($node->operator);

            $attributeConfig = $resourceType->getMapping()->getSubNodeWithPath($node);

            $attributeConfig->applyWhereCondition($query, $operator, $node->compareValue);
        } elseif ($node instanceof Conjunction) {
            foreach ($node->getFactors() as $factor) {
                $query->where(
                    function ($query) use ($factor, $resourceType) {
                        Helper::scimFilterToLaravelQuery($resourceType, $query, $factor);
                    }
                );
            }
        } elseif ($node instanceof Disjunction) {
            foreach ($node->getTerms() as $term) {
                $query->orWhere(
                    function ($query) use ($term, $resourceType) {
                        Helper::scimFilterToLaravelQuery($resourceType, $query, $term);
                    }
                );
            }
        } elseif ($node instanceof ValuePath) {
            // ->filer
            $getAttributePath = function () {
                return $this->attributePath;
            };

            $getFilter = function () {
                return $this->filter;
            };

            $query->whereExists(
                function ($query) {
                    $query->select(DB::raw(1))
                        ->from('users AS users2')
                        ->whereRaw('users.id = users2.id');
                }
            );


        //$node->
        } elseif ($node instanceof Factor) {
            var_dump($node);
            die("Not ok hier!\n");
        }
    }

    /**
     * $scimAttribute could be
     * - urn:ietf:params:scim:schemas:core:2.0:User.userName
     * - userName
     * - urn:ietf:params:scim:schemas:core:2.0:User.userName.name.formatted
     * - urn:ietf:params:scim:schemas:core:2.0:User.emails.value
     * - emails.value
     * - emails.0.value
     * - schemas.0
     *
     * @param  unknown $name
     * @param  unknown $scimAttribute
     * @return AttributeMapping
     */
    public static function getAttributeConfig(ResourceType $resourceType, $scimAttribute)
    {
        $parser = new Parser(Mode::PATH());

        $scimAttribute = preg_replace('/\.[0-9]+$/', '', $scimAttribute);
        $scimAttribute = preg_replace('/\.[0-9]+\./', '.', $scimAttribute);

        $path = $parser->parse($scimAttribute);

        //TODO: FIX this. If $scimAttribute is a schema-indication, it should be considered as a schema
        if ($scimAttribute == 'urn:ietf:params:scim:schemas:core:2.0:User') {
            $attributePath = new AttributePath();
            $attributePath->schema = 'urn:ietf:params:scim:schemas:core:2.0:User';

            $path = Path::fromAttributePath($attributePath);
        }

        return $resourceType->getMapping()->getSubNodeWithPath($path);
    }

    public static function getAttributeConfigOrFail(ResourceType $resourceType, $scimAttribute)
    {
        $result = self::getAttributeConfig($resourceType, $scimAttribute);

        if ($result == null) {
            throw (new SCIMException(sprintf('Unknown attribute "%s"', $scimAttribute)))->setCode(400);
        }

        return $result;
    }

    public static function getEloquentSortAttribute(ResourceType $resourceType, $scimAttribute)
    {
        $mapping = self::getAttributeConfig($resourceType, $scimAttribute);

        if ($mapping == null || $mapping->getSortAttribute() == null) {
            throw (new SCIMException("Invalid sort property"))->setCode(400)->setScimType('invalidFilter');
        }

        return $mapping->getSortAttribute();
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
