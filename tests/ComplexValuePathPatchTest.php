<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\Attribute\Complex;
use ArieTimmerman\Laravel\SCIMServer\Parser\Parser;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class ComplexValuePathPatchTest extends PHPUnitTestCase
{
    public function testRemoveOperationWithValuePathFilterRemovesMatchingElement(): void
    {
        $model = new class extends Model {
            protected $table = 'users';
            public $timestamps = false;
        };

        $model->emails = [
            ['value' => 'work@example.com', 'type' => 'work'],
            ['value' => 'home@example.com', 'type' => 'home'],
        ];

        $attribute = $this->makeMultiValuedComplexAttribute();

        $path = Parser::parse('emails[type eq "work"]')->shiftValuePathAttributes();

        $attribute->patch('remove', null, $model, $path);

        $this->assertCount(1, $model->emails);
        $this->assertSame('home@example.com', $model->emails[0]['value']);
    }

    public function testReplaceOperationWithValuePathFilterUpdatesSubAttribute(): void
    {
        $model = new class extends Model {
            protected $table = 'users';
            public $timestamps = false;
        };

        $model->emails = [
            ['value' => 'work@example.com', 'type' => 'work'],
            ['value' => 'home@example.com', 'type' => 'home'],
        ];

        $attribute = $this->makeMultiValuedComplexAttribute();

        $path = Parser::parse('emails[type eq "work"].value');
        $path->shiftValuePathAttributes();

        $attribute->patch('replace', 'new-work@example.com', $model, $path);

        $this->assertSame('new-work@example.com', $model->emails[0]['value']);
        $this->assertSame('home@example.com', $model->emails[1]['value']);
    }

    private function makeMultiValuedComplexAttribute(): Complex
    {
        return new class('emails') extends Complex {
            public function __construct($name)
            {
                parent::__construct($name);
                $this->setMultiValued(true);
            }

            protected function doRead(&$object, $attributes = [])
            {
                return $object->{$this->name} ?? [];
            }

            public function replace($value, Model &$object, Path $path = null, $removeIfNotSet = false)
            {
                $object->{$this->name} = $value;
                $this->dirty = true;
            }
        };
    }
}
