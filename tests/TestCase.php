<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\ServiceProvider;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\Group;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\User;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $baseUrl = 'http://localhost';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations('testbench');

        Schema::create('groups', function (Blueprint $table) {
            $table->increments('id');
            // timestamp columns
            $table->timestamps();
            $table->string('name')->nullable();
            $table->string('displayName')->nullable();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->increments('id');

            $table->uuid('group_id');
            $table->uuid('user_id');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');

            $table->unique(['user_id', 'group_id']);

            $table->timestamps();
        });

        $this->withFactories(realpath(dirname(__DIR__) . '/database/factories'));

        \ArieTimmerman\Laravel\SCIMServer\RouteProvider::routes();

        $users = factory(User::class, 100)->create();
        $groups = factory(Group::class, 100)->create();

        $users->each(function ($user) use ($groups) {
            $user->groups()->attach(
                $groups->random(rand(1, 3))->pluck('id')->toArray()
            );
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        $app ['config']->set('app.url', 'http://localhost');
        $app ['config']->set('app.debug', true);

        $app->register(ServiceProvider::class);

        // Setup default database to use sqlite :memory:
        $app['config']->set('scimserver.Users.class', \ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class);
        $app['config']->set('auth.providers.users.model', \ArieTimmerman\Laravel\SCIMServer\Tests\Model\User::class);
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

}