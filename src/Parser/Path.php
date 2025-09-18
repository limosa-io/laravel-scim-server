<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\AttributePath as AstAttributePath;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ComparisonExpression;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Path as AstPath;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ValuePath as AstValuePath;

class Path
{
    public mixed $node;

    public mixed $text;

    private ?AttributePath $attributePath;

    private ?ValuePath $valuePath;

    public function __construct(mixed $node, mixed $text)
    {
        $this->node = $node;
        $this->text = $text;

        $this->attributePath = $this->wrapAttributePath($this->extractAttributePath($node));
        $this->valuePath = $this->wrapValuePath($this->extractValuePath($node));
    }

    public function getAttributePath(): ?AttributePath
    {
        return $this->attributePath;
    }

    public function setAttributePath(?AttributePath $attributePath): void
    {
        $this->attributePath = $attributePath;
    }

    public function getValuePath(): ?ValuePath
    {
        return $this->valuePath;
    }

    public function setValuePath(?ValuePath $valuePath): void
    {
        $this->valuePath = $valuePath;
    }

    public function getValuePathAttributes(): array
    {
        return $this->valuePath?->getAttributePath()?->getAttributeNames() ?? [];
    }

    public function getAttributePathAttributes(): array
    {
        return $this->attributePath?->getAttributeNames() ?? [];
    }

    public function getValuePathFilter(): ?Filter
    {
        return $this->valuePath?->getFilter();
    }

    public function setValuePathFilter(?Filter $filter): void
    {
        $this->valuePath?->setFilter($filter);
    }

    public function shiftValuePathAttributes(): self
    {
        $valuePath = $this->getValuePath();
        if ($valuePath !== null) {
            $valuePath->getAttributePath()->shiftAttributeName();
        }

        return $this;
    }

    public function shiftAttributePathAttributes(): self
    {
        $attributePath = $this->getAttributePath();
        if ($attributePath !== null) {
            $attributePath->shiftAttributeName();

            if (empty($this->getAttributePathAttributes())) {
                $this->setAttributePath(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        if (is_array($this->text)) {
            return json_encode($this->text);
        }

        return (string)$this->text;
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->getAttributePathAttributes())
            || !empty($this->getValuePathAttributes())
            || $this->getValuePathFilter() !== null;
    }

    private function extractAttributePath(mixed $node): ?AstAttributePath
    {
        if ($node instanceof AstPath) {
            return $node->getAttributePath();
        }

        if ($node instanceof ComparisonExpression) {
            return $node->attributePath;
        }

        if ($node instanceof AstValuePath) {
            return $node->getAttributePath();
        }

        return null;
    }

    private function extractValuePath(mixed $node): ?AstValuePath
    {
        if ($node instanceof AstPath) {
            return $node->getValuePath();
        }

        if ($node instanceof AstValuePath) {
            return $node;
        }

        return null;
    }

    private function wrapAttributePath(?AstAttributePath $attributePath): ?AttributePath
    {
        return $attributePath !== null ? new AttributePath($attributePath) : null;
    }

    private function wrapValuePath(?AstValuePath $valuePath): ?ValuePath
    {
        return $valuePath !== null ? new ValuePath($valuePath) : null;
    }
}
