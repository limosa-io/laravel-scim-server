<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter;

class Token
{
    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly int $position
    ) {
    }
}
