<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use Tmilos\ScimSchema\Builder\SchemaBuilderV2;
use ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;

class SchemaController extends Controller
{
    public function getSchemas()
    {
        $config = resolve(SCIMConfig::class)->getConfig();

        $schemaNodes = [];
        $schemas = [];

        foreach ($config as $key => $value) {
            $value['map']->generateSchema();

            $schemaNodes = array_merge($schemaNodes, $value['map']->getSchemaNodes());
        }

        foreach ($schemaNodes as $schemaNode) {
            $schemas[] = $schemaNode->generateSchema();
        }

        return $schemas;
    }

    public function show($id)
    {
        $result = collect($this->getSchemas())->first(
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
        $schemas = collect($this->getSchemas());
        return new ListResponse($schemas, 1, $schemas->count());
    }
}
