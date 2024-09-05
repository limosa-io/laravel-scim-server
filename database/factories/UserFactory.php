<?php

use Faker\Generator;

$factory->define(ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class, function (Generator $faker) {
    return [
       // 'username' => $faker->userName,
        'email' => $faker->unique()->email,
        'formatted' => $faker->name,
        'name' => $faker->name,
        'password'=>'test',
        'active' => false
    ];
});
