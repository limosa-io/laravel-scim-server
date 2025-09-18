<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Ast;

class Conjunction extends Term
{
    /** @var Filter[] */
    private array $factors = [];

    /**
     * @param Filter[] $factors
     */
    public function __construct(array $factors = [])
    {
        foreach ($factors as $factor) {
            $this->add($factor);
        }
    }

    public function add(Filter $factor): void
    {
        $this->factors[] = $factor;
    }

    /**
     * @return Filter[]
     */
    public function getFactors(): array
    {
        return $this->factors;
    }

    public function __toString(): string
    {
        return implode(' and ', $this->factors);
    }

    public function dump(): array
    {
        $parts = [];
        foreach ($this->factors as $factor) {
            $parts[] = $factor->dump();
        }

        return [
            'Conjunction' => $parts,
        ];
    }
}
