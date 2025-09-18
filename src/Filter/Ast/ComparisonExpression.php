<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Ast;

class ComparisonExpression extends Factor
{
    public AttributePath $attributePath;

    public string $operator;

    public mixed $compareValue;

    public function __construct(AttributePath $attributePath, string $operator, mixed $compareValue = null)
    {
        $this->attributePath = $attributePath;
        $this->operator = strtolower($operator);
        $this->compareValue = $compareValue;
    }

    public function __toString(): string
    {
        if ($this->operator === 'pr') {
            return sprintf('%s %s', $this->attributePath, $this->operator);
        }

        $value = $this->compareValue;
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d\TH:i:s\Z');
        } elseif (is_string($value)) {
            $value = '"' . $value . '"';
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif ($value === null) {
            $value = 'null';
        }

        return sprintf('%s %s %s', $this->attributePath, $this->operator, $value);
    }

    public function dump(): array
    {
        return [
            'ComparisonExpression' => (string) $this,
        ];
    }
}
