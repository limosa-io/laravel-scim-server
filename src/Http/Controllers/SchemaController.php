<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use Tmilos\ScimSchema\Builder\SchemaBuilderV2;
use ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;

class SchemaController extends Controller
{
    private $schemas = null;

    public function getSchemas()
    {

        $this->generateSchema();
        if ($this->schemas != null) {
            return $this->schemas;
        }

        $config = resolve(SCIMConfig::class)->getConfig();

        $schemas = [];

        foreach ($config as $key => $value) {
            if ($key != 'Users' && $key != 'Groups') {
                continue;
            }


            foreach ($value['schema'] as $s) {
                $schema = (new SchemaBuilderV2())->get($s);

                if ($schema == null) {
                    continue;
                    throw new SCIMException("Schema not found");
                }

                $schema->getMeta()->setLocation(route('scim.schemas', ['id' => $schema->getId()]));

                $schemas[] = $schema->serializeObject();
            }
        }

        $this->schemas = collect($schemas);

        return $this->schemas;
    }

    public function show($id)
    {
        $result = $this->getSchemas()->first(
            function ($value, $key) use ($id) {
                return $value['id'] == $id;
            }
        );

        if ($result == null) {
            throw (new SCIMException(sprintf('Resource "%s" not found', $id)))->setCode(404);
        }

        return $result;
    }

    public function index()
    {
        return new ListResponse($this->getSchemas(), 1, $this->getSchemas()->count());
    }

    public function generateSchemaPart($value)
    {
    }

    public function generateSchema()
    {
        $config = resolve(SCIMConfig::class)->getConfig();

        $schemas = [];

        foreach ($config as $key => $value) {
            foreach ($value['mapping'] as $k => $v) {
                // $key contains :
                if (strpos($k, ':') !== false) {
                    foreach ($v as $attribute => $value) {
                        var_dump($attribute);
                    }
                }
            }
        }
    }
}
