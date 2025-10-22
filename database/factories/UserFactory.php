<?php

use Faker\Generator;

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class, function (Generator $faker) {
    $first = $faker->firstName;
    $last  = $faker->lastName;

    $formatted = "{$first} {$last}";
    $base = strtolower(substr($first, 0, 1) . preg_replace('/[^a-z0-9]/i', '', $last));
    $suffix = $faker->numberBetween(100, 9999); // helps avoid collisions
    $username = $base . $suffix;

    return [
        'email'     => "{$username}@example.test",
        'formatted' => $formatted,
        'name'      => $username, // login-style username
        'password'  => 'test',
        'active'    => $faker->boolean,
    ];
});
