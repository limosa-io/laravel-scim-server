<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attribute\Complex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class ResourceType
{
    protected $name = null;

    public function __construct($name, protected $configuration)
    {
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function getMapping(): Complex
    {
        return $this->configuration['map'];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSchema()
    {
        return $this->getMapping()->getSchemas();
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

    public function getQuery(): Builder
    {
        return Arr::get($this->configuration, 'query') ?? $this->getClass()::query();
    }

    public function getValidations()
    {
        return $this->getMapping()->getValidations();
    }

    public function getWithRelations()
    {
        return $this->configuration['withRelations'] ?? [];
    }

    public static function user()
    {
        return new ResourceType('Users', resolve(SCIMConfig::class)->getUserConfig());
    }
}
