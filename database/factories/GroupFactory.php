<?php

use ArieTimmerman\Laravel\SCIMServer\Tests\Model\Group;
use Faker\Generator;

$factory->define(Group::class, function (Generator $faker) {
    return [
        'displayName' => $faker->company
    ];
});
