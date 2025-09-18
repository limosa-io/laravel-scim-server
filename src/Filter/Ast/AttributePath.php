<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Ast;

class AttributePath extends Node
{
    public ?string $schema = null;

    /** @var string[] */
    public array $attributeNames = [];

    public function __construct(?string $schema = null, array $attributeNames = [])
    {
        $this->schema = $schema;
        $this->attributeNames = array_values($attributeNames);
    }

    public function add(string $attributeName): void
    {
        $this->attributeNames[] = $attributeName;
    }

    public function __toString(): string
    {
        $path = implode('.', $this->attributeNames);

        if ($this->schema !== null && $this->schema !== '') {
            return $this->schema . ':' . $path;
        }

        return $path;
    }

    public function dump(): array
    {
        return [
            'AttributePath' => (string) $this,
        ];
    }

    public function shift(): ?string
    {
        return array_shift($this->attributeNames);
    }

    /**
     * @return string[]
     */
    public function getAttributeNames(): array
    {
        return $this->attributeNames;
    }
}
