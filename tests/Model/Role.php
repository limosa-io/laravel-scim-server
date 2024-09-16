<?php
namespace ArieTimmerman\Laravel\SCIMServer\Tests\Model;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    
    protected $fillable = ['value', 'display', 'type'];

    // primary key is value, of type string
    public $incrementing = false;
    protected $primaryKey = 'value';
    protected $keyType = 'string';

}