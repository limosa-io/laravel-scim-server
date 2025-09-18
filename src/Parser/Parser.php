<?php

namespace ArieTimmerman\Laravel\SCIMServer\Parser;

use ArieTimmerman\Laravel\SCIMServer\Filter\FilterParser;

class Parser
{
    public static function parse(?string $input): ?Path
    {
        if ($input === null) {
            return null;
        }

        $node = (new FilterParser())->parsePath($input);

        return new Path($node, $input);
    }

    public static function parseFilter(?string $input): ?Path
    {
        if ($input === null) {
            return null;
        }

        $node = (new FilterParser())->parseFilter($input);

        return new Path($node, $input);
    }
}
