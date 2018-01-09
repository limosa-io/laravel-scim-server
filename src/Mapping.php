<?php
namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;
use Illuminate\Contracts\Support\Arrayable;
use Tmilos\ScimFilterParser\Ast\Path;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use ArieTimmerman\Laravel\SCIMServer\Attribute\UnmapedAttributeMapping;
use Tmilos\ScimFilterParser\Ast\Node;
use ArieTimmerman\Laravel\SCIMServer\Attribute\MultiValued;

class Mapping {
    
    protected $mapping = null;
    protected $resourceType = null;
    
    function __construct(array $mapping, $resourceType){
        $this->mapping = $mapping;   
        $this->resourceType = $resourceType;
    }
    
    public function getMapping($attribute){
        die("in get mapping!");
        if($attribute == null) {
            return $this->mapping;
        }else{
            return $this->mapping[$attribute];
        }
        
    }
    
    public function getSubNode($sub) : AttributeMapping{
        
        die("not used!");
        $result = null;
        
        if(array_key_exists($sub, $this->toArray())){
            $result = $this->toArray()[$sub];
        }else{
            throw new SCIMException("Unknown attribute!");   
        }
        
        return AttributeMapping::ensureAttributeMappingObject($result, $this);
        
    }
    
    public function getNode($path) : AttributeMapping{
        
        if($path == null){
            return $this;
        }
        
        $result = $this;
        
        $schema = $path->schema;
    
        if($schema == null && !in_array($path->attributeNames[0], Schema::ATTRIBUTES_CORE)){
            $schema = $this->resourceType->getSchema();
        }
        
        $elements = array_merge([$schema],$path->attributeNames);
                
        if($this->resourceType->getConfiguration()['map_unmapped'] && $schema == $this->resourceType->getConfiguration()['unmapped_namespace']){
            
            die("before UnmapedAttributeMapping!");
            return new UnmapedAttributeMapping($path);    
            
        } else{
            
            foreach ($elements as $value) {
                $result = $result->getSubNode($value);
            }
            
        }
                
        return $result;
        
    }
    
    public function getMappingByPath(Path $path) : AttributeMapping{
                 
        $getAttributePath = function() {
            return $this->attributePath;
        };
        
        $getValuePath = function() {
            return $this->valuePath;
        };
        
        $getFilter = function() {
            return $this->filter;
        };
        
        return $this->getNode( @$getAttributePath->call($getValuePath->call($path)) )->withFilter( @$getFilter->call($getValuePath->call($path)) )->getSubNodeWithPath( $getAttributePath->call($path) );
    
    }
    
    public function toArray(){
        return $this->mapping;
    }
    
    public function withFilter($filter){
        
        if($filter == null){
            return $this;
        }else{
            throw new SCIMException('Filters not supported!');
        }
        
    }
    
}