<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests\Model;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    public function members()
    {
        return $this
            ->belongsToMany(User::class);
    }
}
