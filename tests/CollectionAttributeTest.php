<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Attribute\Collection;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class CollectionAttributeTest extends PHPUnitTestCase
{
    public function testRelationshipNameDefaultsToAttributeName(): void
    {
        $collection = new Collection('members');

        $this->assertSame('members', $collection->getRelationshipName());
    }

    public function testRelationshipNameUsesExplicitAttributeWhenProvided(): void
    {
        $collection = new Collection('members', 'groupMembers');

        $this->assertSame('groupMembers', $collection->getRelationshipName());
    }
}
