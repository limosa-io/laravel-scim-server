<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use Orchestra\Testbench\TestCase;

class BasicTest extends TestCase {
	
	public function setUp()
	{
		parent::setUp();
		
		//TODO: Overide Paginator with alias???

		// Or with custom provider??     	//Illuminate\Pagination\LengthAwarePaginator
		
		$this->withFactories(realpath(dirname(__DIR__).'/database/factories'));
	}

	protected function getEnvironmentSetUp($app) {

		$app ['config']->set ( 'scimserver', include realpath(dirname(__DIR__).'/config/scimserver.php') );

// 		$app->bind('Illuminate\Pagination\LengthAwarePaginator', function ($app) {
// 			die("Yeah!");
// 		});
		
	}
	
	public function testUrlCode() {
		
				
		$users = factory(\ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class, 1000)->make();

		$user_count = count($users) >= 100;

		$paginator = new \ArieTimmerman\Laravel\SCIMServer\SCIM\ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse($users);

		echo "asdggsd\n";
		echo $paginator->toJson(JSON_PRETTY_PRINT);

		// foreach($users as $user){

		// 	//echo $user->toJson(JSON_PRETTY_PRINT);
		// 	echo $user->toSCIMJson(JSON_PRETTY_PRINT);
			
		// }

		
		
		$this->assertTrue($user_count);
		
	}
	
	
	
}
            
            
            