<?php
namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;
use Illuminate\Contracts\Support\Arrayable;

class Helper
{

    public static function getMappedValue(&$user, $valueMapping, &$uses = [])
    {
        $v = $valueMapping;
        
        if ($v instanceof AttributeMapping) {
            
            $v = $valueMapping->read($user);
            
            if (is_string($valueMapping->eloquentAttribute)) {
                $uses[] = $valueMapping->eloquentAttribute;
            }
        }
        
        if (is_scalar($v)) {
            
            $v = $v;
        } else 
            if (is_array($v)) {
                
                $vNew = [];
                
                foreach ($v as $j => $l) {
                    
                    $result = Helper::getMappedValue($user, $l, $uses);
                    
                    //TODO: Optionally, include null values
                    if ($result != null) {
                        $vNew[$j] = $result;
                    }
                }
                
                $v = $vNew;
            } else 
                if ($v instanceof AttributeMapping) {
                    $v = Helper::getMappedValue($user, $v, $uses);
                }
        
        return $v;
    }

    public static function isAssoc($array)
    {
        return (array_values($array) !== $array);
    }
    
    // var_dump(class_uses(config('auth.providers.users.model')));exit;
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
        
        if (! empty($object) && is_object($object[0])) {
            
            if (! in_array('ArieTimmerman\Laravel\SCIMServer\Traits\SCIMResource', class_uses(get_class($object[0])))) {
                
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
        $userArray = null;
        
        if (method_exists($object, "toArray_fromParent")) {
            $userArray = $object->toArray_fromParent();
        } else {
            
            // TODO: use something like the following. Seems to be broken somehow
            // $setDateFormat = function() {
            // $this->dateFormat = 'c';
            // };
            
            // $setDateFormat->call($object);
            
            $userArray = $object->toArray();
            
            if (method_exists($object, 'getDates')) {
                
                $dateAttributes = $object->getDates();
                foreach ($dateAttributes as $dateAttribute) {
                    if (isset($userArray[$dateAttribute])) {
                        $userArray[$dateAttribute] = $object->getAttribute($dateAttribute)->format('c');
                    }
                }
            }
        }
        
        $result = [];
        
        if ($resourceType != null) {
            
            $mapping = $resourceType->getMapping();
            
            $uses = [];
            
            foreach ($mapping as $key => $value) {
                
                preg_match("/^(.*?)(\\.\\*)?$/", $key, $matches);
                
                $key = $matches[1];
                
                if ($value instanceof AttributeMapping && is_string($value->eloquentAttribute)) {
                    unset($userArray[$value->eloquentAttribute]);
                }
                
                $v = Helper::getMappedValue($object, $value, $uses);
                
                if ($v !== null) {
                    
                    if (isset($matches[2]) && ! empty($matches[2])) {
                        $result[$key] = [];
                        $result[$key][] = $v;
                    } else {
                        $result[$key] = $v;
                    }
                    
                }
                
            }
            //exit;
            
            foreach ($uses as $key) {
                unset($userArray[$key]);
            }
            
            if (! empty($userArray) && $resourceType->getConfiguration()['map_unmapped']) {
                
                $namespace = $resourceType->getConfiguration()['unmapped_namespace'];
                
                $result[$namespace] = [];
                
                foreach ($userArray as $key => $value) {
                    $result[$namespace][$key] = AttributeMapping::eloquentAttributeToString($value);
                }
            }
            
        }else{
            $result = $userArray;
        }
        
        return $result;
    }
}