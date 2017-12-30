<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests\Model;

use ArieTimmerman\Laravel\SCIMServer\Traits\SCIMResource;

class User extends \Illuminate\Foundation\Auth\User{

	use SCIMResource;
	
}


