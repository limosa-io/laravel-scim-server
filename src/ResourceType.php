<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use Illuminate\Support\Arr;
use ArieTimmerman\Laravel\SCIMServer\Attribute\AttributeMapping;

class ResourceType
{
    protected $configuration = null;

    protected $name = null;

    public function __construct($name, $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function getMapping()
    {
        $mapping = $this->configuration['mapping'];

        if (!isset($mapping['schema'])) {
            $mapping['schemas'] = AttributeMapping::constant($this->configuration['schema'])->ignoreWrite();
        }
        return AttributeMapping::object($mapping ?? [])->setDefaultSchema($this->configuration['schema']);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSchema()
    {
        return $this->configuration['schema'];
    }

    public function getClass()
    {
        return $this->configuration['class'];
    }

    public function getFactory()
    {
        return $this->configuration['factory'] ?? function () {
            $class = $this->getClass();

            return new $class();
        };
    }

    public function getQuery()
    {
        return Arr::get($this->configuration, 'query') ?? $this->getClass()::query();
    }

    public function getValidations()
    {
        return $this->configuration['validations'];
    }

    public function getWithRelations()
    {
        return $this->configuration['withRelations'] ?? [];
    }

    public static function user()
    {
        return new ResourceType('Users', resolve(SCIMConfig::class)->getUserConfig());
    }

    public function getAllAttributeConfigs($mapping = -1)
    {
        $result = [];

        if ($mapping == -1) {
            $mapping = $this->getMapping();
        }

        foreach ($mapping as $key => $value) {
            if ($value instanceof AttributeMapping && $value != null) {
                $result[] = $value;
            } elseif (is_array($value)) {
                $extra = $this->getAllAttributeConfigs($value);

                if (!empty($extra)) {
                    $result = array_merge($result, $extra);
                }
            }
        }

        return $result;
    }
}
