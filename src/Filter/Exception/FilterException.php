<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Exception;

use RuntimeException;

class FilterException extends RuntimeException
{
    public static function syntaxError(string $message, string $input, int $position): self
    {
        $prefix = sprintf('line 0, col %d: Error: %s at position %d', $position, $message, $position);
        return new self($prefix . ', input: ' . $input);
    }
}
