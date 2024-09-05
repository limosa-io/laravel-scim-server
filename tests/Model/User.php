<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests\Model;

class User extends \Illuminate\Foundation\Auth\User
{

    protected $casts = [
        'active' => 'boolean',
    ];

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }
}
