<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Ast;

class Path extends Node
{
    public function __construct(private ?AttributePath $attributePath = null, private ?ValuePath $valuePath = null)
    {
    }

    public static function fromAttributePath(AttributePath $attributePath): self
    {
        return new self($attributePath, null);
    }

    public static function fromValuePath(ValuePath $valuePath, ?AttributePath $attributePath = null): self
    {
        return new self($attributePath, $valuePath);
    }

    public function getAttributePath(): ?AttributePath
    {
        return $this->attributePath;
    }

    public function getValuePath(): ?ValuePath
    {
        return $this->valuePath;
    }

    public function dump(): array
    {
        if ($this->valuePath === null) {
            return [
                'Path' => $this->attributePath?->dump(),
            ];
        }

        $parts = $this->valuePath->dump();
        if ($this->attributePath !== null) {
            $parts[] = $this->attributePath->dump();
        }

        return ['Path' => $parts];
    }
}
