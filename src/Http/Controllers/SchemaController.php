<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use Tmilos\ScimSchema\Builder\SchemaBuilderV2;
use ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class SchemaController extends Controller{

	private $schemas = null;
	
	function __construct(){
	
		$config = config("scimserver");
	
		$schemas = [];
	
		foreach($config as $key => $value){
			$schema = (new SchemaBuilderV2())->get($value['schema']);
			
			if($schema == null){
				throw new SCIMException("Schema not found");	
			}
			
			$schema->getMeta()->setLocation(route('scim.schemas', ['id' => $schema->getId()]));
			
			$schemas[] = $schema->serializeObject();
		}
	
		$this->schemas = collect($schemas);
	
	}
	
	public function show($id){
		
		$result = $this->schemas->first(function($value, $key) use ($id) {
			return $value['id'] == $id;
		});
		 
		if($result == null){
			throw (new SCIMException(sprintf('Resource "%s" not found',$id)))->setCode(404);
		}
		 
		return $result;
		
	}
	
    public function index(){

    	return new ListResponse($this->schemas,1,$this->schemas->count());

    }

}