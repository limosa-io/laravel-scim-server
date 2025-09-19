<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Ast;

class Disjunction extends Term
{
    /** @var Term[] */
    private array $terms = [];

    /**
     * @param Term[] $terms
     */
    public function __construct(array $terms = [])
    {
        foreach ($terms as $term) {
            $this->add($term);
        }
    }

    public function add(Term $term): void
    {
        $this->terms[] = $term;
    }

    /**
     * @return Term[]
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    public function __toString(): string
    {
        return implode(' or ', $this->terms);
    }

    public function dump(): array
    {
        $parts = [];
        foreach ($this->terms as $term) {
            $parts[] = $term->dump();
        }

        return [
            'Disjunction' => $parts,
        ];
    }
}
