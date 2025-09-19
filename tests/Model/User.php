<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests\Model;

use Illuminate\Database\Eloquent\Casts\AsCollection;

class User extends \Illuminate\Foundation\Auth\User
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'roles' => AsCollection::class,
    ];

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }
}
