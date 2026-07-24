<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Filter\Exception\FilterException;
use ArieTimmerman\Laravel\SCIMServer\Filter\FilterParser;
use PHPUnit\Framework\TestCase;

class FilterParserTest extends TestCase
{
    public function testAcceptsDollarRefAttributePath(): void
    {
        $path = (new FilterParser())->parsePath('manager.$ref');

        $this->assertNotNull($path);
    }

    public function testRejectsUnknownDollarPrefixedAttribute(): void
    {
        $this->expectException(FilterException::class);
        $this->expectExceptionMessageMatches('/Invalid attribute name "\$invalid"/');

        (new FilterParser())->parsePath('$invalid');
    }

    public function testRejectsEmbeddedDollarSign(): void
    {
        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('Invalid attribute name "foo$bar"');

        (new FilterParser())->parsePath('foo$bar');
    }
}
