<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter\Ast;

abstract class Node
{
    abstract public function dump(): array;
}
